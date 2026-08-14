<?php

namespace Modules\Fiscal\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Fiscal\Domain\Entities\DteDocument;
use Modules\Fiscal\Domain\ValueObjects\DteStatus;
use Modules\Fiscal\Domain\ValueObjects\DteType;

class SalesBookController extends Controller
{
    /**
     * GET /api/v1/fiscal/sales-book
     * Genera el Libro de Ventas (SII) para un período.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $startDate = $request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', now()->endOfMonth()->toDateString());

        // Validar rango de fechas
        if ($startDate > $endDate) {
            return response()->json([
                'success' => false,
                'message' => 'La fecha inicial no puede ser mayor a la fecha final.',
            ], 422);
        }

        // Obtener DTEs aceptados del período
        $dtes = DteDocument::where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('sii_status', DteStatus::ACCEPTED)
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->orderBy('issue_date')
            ->orderBy('folio')
            ->get();

        // Agrupar por tipo de DTE
        $byType = $dtes->groupBy('dte_type');

        $summary = [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'total_documents' => $dtes->count(),
            'total_net' => (float) $dtes->sum('net_amount'),
            'total_tax' => (float) $dtes->sum('tax_amount'),
            'total_exempt' => (float) $dtes->sum('exempt_amount'),
            'total_amount' => (float) $dtes->sum('total_amount'),
            'by_type' => [],
        ];

        foreach (DteType::cases() as $type) {
            $typeDtes = $byType->get($type->value, collect());
            if ($typeDtes->isNotEmpty()) {
                $summary['by_type'][] = [
                    'dte_type' => $type->value,
                    'dte_type_label' => $type->label(),
                    'documents_count' => $typeDtes->count(),
                    'total_net' => (float) $typeDtes->sum('net_amount'),
                    'total_tax' => (float) $typeDtes->sum('tax_amount'),
                    'total_exempt' => (float) $typeDtes->sum('exempt_amount'),
                    'total_amount' => (float) $typeDtes->sum('total_amount'),
                ];
            }
        }

        return response()->json(['data' => $summary]);
    }

    /**
     * GET /api/v1/fiscal/sales-book/csv
     * Descarga el Libro de Ventas en formato CSV.
     */
    public function exportCsv(Request $request)
    {
        $user = $request->user();

        $startDate = $request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', now()->endOfMonth()->toDateString());

        $dtes = DteDocument::where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('sii_status', DteStatus::ACCEPTED)
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->orderBy('issue_date')
            ->orderBy('folio')
            ->get();

        // Generar CSV
        $csv = "Tipo DTE;Folio;Fecha;RUT Receptor;Razón Social;Neto;IVA;Exento;Total;Track ID\n";
        
        foreach ($dtes as $dte) {
            $csv .= implode(';', [
                $dte->dte_type->value,
                $dte->folio,
                $dte->issue_date->toDateString(),
                $dte->receiver_rut ?? '66666666-6',
                $dte->receiver_business_name ?? 'Consumidor Final',
                number_format($dte->net_amount, 0, ',', '.'),
                number_format($dte->tax_amount, 0, ',', '.'),
                number_format($dte->exempt_amount, 0, ',', '.'),
                number_format($dte->total_amount, 0, ',', '.'),
                $dte->track_id ?? 'N/A',
            ]) . "\n";
        }

        $filename = "libro_ventas_{$startDate}_{$endDate}.csv";

        return response($csv, 200)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }
}
