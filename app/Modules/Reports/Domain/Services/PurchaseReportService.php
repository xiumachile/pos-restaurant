<?php

namespace Modules\Reports\Domain\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PurchaseReportService
{
    /**
     * KPIs de compras en un rango
     */
    public function getKPIs(int $companyId, int $branchId, Carbon $from, Carbon $to): array
    {
        $purchases = DB::table('raw_ingredient_purchases as p')
            ->join('raw_ingredients as i', 'i.id', '=', 'p.raw_ingredient_id')
            ->where('i.company_id', $companyId)
            ->where('i.branch_id', $branchId)
            ->whereBetween('p.purchase_date', [$from, $to])
            ->get();

        $totalAmount = $purchases->sum('total_purchase_cost');
        $count = $purchases->count();
        $averageTicket = $count > 0 ? (int) round($totalAmount / $count) : 0;
        $withDocumentCount = $purchases->filter(fn($p) => !empty($p->document_type))->count();

        return [
            'total_amount' => (int) $totalAmount,
            'purchases_count' => $count,
            'average_ticket' => $averageTicket,
            'with_document_count' => $withDocumentCount,
        ];
    }

    /**
     * Distribución por tipo de documento (SII)
     */
    public function getByDocumentType(int $companyId, int $branchId, Carbon $from, Carbon $to): array
    {
        return DB::table('raw_ingredient_purchases as p')
            ->join('raw_ingredients as i', 'i.id', '=', 'p.raw_ingredient_id')
            ->where('i.company_id', $companyId)
            ->where('i.branch_id', $branchId)
            ->whereBetween('p.purchase_date', [$from, $to])
            ->whereNotNull('p.document_type')
            ->select(
                'p.document_type',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(p.total_purchase_cost) as total_amount')
            )
            ->groupBy('p.document_type')
            ->orderByDesc('total_amount')
            ->get()
            ->map(function ($row) {
                return [
                    'document_type' => $row->document_type,
                    'count' => (int) $row->count,
                    'total_amount' => (int) $row->total_amount,
                ];
            })
            ->toArray();
    }

    /**
     * Top proveedores por monto
     */
    public function getTopSuppliers(int $companyId, int $branchId, Carbon $from, Carbon $to, int $limit = 10): array
    {
        return DB::table('raw_ingredient_purchases as p')
            ->join('raw_ingredients as i', 'i.id', '=', 'p.raw_ingredient_id')
            ->where('i.company_id', $companyId)
            ->where('i.branch_id', $branchId)
            ->whereBetween('p.purchase_date', [$from, $to])
            ->whereNotNull('p.supplier_name')
            ->select(
                'p.supplier_name',
                'p.supplier_rut',
                DB::raw('COUNT(*) as purchases_count'),
                DB::raw('SUM(p.total_purchase_cost) as total_amount')
            )
            ->groupBy('p.supplier_name', 'p.supplier_rut')
            ->orderByDesc('total_amount')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                return [
                    'supplier_name' => $row->supplier_name,
                    'supplier_rut' => $row->supplier_rut,
                    'purchases_count' => (int) $row->purchases_count,
                    'total_amount' => (int) $row->total_amount,
                ];
            })
            ->toArray();
    }

    /**
     * Top insumos comprados
     */
    public function getTopIngredients(int $companyId, int $branchId, Carbon $from, Carbon $to, int $limit = 10): array
    {
        return DB::table('raw_ingredient_purchases as p')
            ->join('raw_ingredients as i', 'i.id', '=', 'p.raw_ingredient_id')
            ->where('i.company_id', $companyId)
            ->where('i.branch_id', $branchId)
            ->whereBetween('p.purchase_date', [$from, $to])
            ->select(
                'i.name_translations',
                'i.sku as ingredient_sku',
                'i.base_unit',
                DB::raw('COUNT(*) as purchases_count'),
                DB::raw('SUM(p.total_base_quantity_added) as total_quantity'),
                DB::raw('SUM(p.total_purchase_cost) as total_amount')
            )
            ->groupBy('i.id', 'i.name_translations', 'i.sku', 'i.base_unit')
            ->orderByDesc('total_amount')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $translations = is_string($row->name_translations)
                    ? json_decode($row->name_translations, true)
                    : $row->name_translations;

                return [
                    'ingredient_name' => $translations['es'] ?? $translations['zh'] ?? $row->ingredient_sku,
                    'ingredient_sku' => $row->ingredient_sku,
                    'base_unit' => $row->base_unit,
                    'purchases_count' => (int) $row->purchases_count,
                    'total_quantity' => (float) $row->total_quantity,
                    'total_amount' => (int) $row->total_amount,
                ];
            })
            ->toArray();
    }
}
