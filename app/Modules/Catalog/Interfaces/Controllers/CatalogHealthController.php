<?php

namespace Modules\Catalog\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogHealthController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $branchId = $request->user()->branch_id;

        $defaultPriceList = DB::table('price_lists')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('is_default', true)
            ->whereNull('deleted_at')
            ->first();

        $defaultMenu = DB::table('menus')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        $productsWithoutMenu = DB::table('products as p')
            ->leftJoin('menu_products as mp', 'mp.product_id', '=', 'p.id')
            ->leftJoin('menus as m', function ($join) {
                $join->on('m.id', '=', 'mp.menu_id')
                     ->whereNull('m.deleted_at')
                     ->where('m.is_active', true);
            })
            ->where('p.branch_id', $branchId)
            ->where('p.company_id', $companyId)
            ->where('p.is_active', true)
            ->whereNull('p.deleted_at')
            ->whereNull('m.id')
            ->count();

        $productsWithoutDefaultPrice = 0;
        if ($defaultPriceList) {
            $productsWithoutDefaultPrice = DB::table('products as p')
                ->leftJoin('product_prices as pp', function ($join) use ($defaultPriceList) {
                    $join->on('pp.product_id', '=', 'p.id')
                         ->where('pp.price_list_id', $defaultPriceList->id);
                })
                ->where('p.branch_id', $branchId)
                ->where('p.company_id', $companyId)
                ->where('p.is_active', true)
                ->whereNull('p.deleted_at')
                ->whereNull('pp.id')
                ->count();
        }

        $healthy = $defaultPriceList !== null
            && $defaultMenu !== null
            && $productsWithoutMenu === 0
            && $productsWithoutDefaultPrice === 0;

        return response()->json([
            'success' => true,
            'data' => [
                'healthy' => $healthy,
                'branch_id' => $branchId,
                'company_id' => $companyId,
                'checks' => [
                    'has_default_price_list' => [
                        'ok' => $defaultPriceList !== null,
                        'detail' => $defaultPriceList ? ($defaultPriceList->display_name ?? $defaultPriceList->name) : null,
                    ],
                    'has_default_menu' => [
                        'ok' => $defaultMenu !== null,
                        'detail' => $defaultMenu ? $defaultMenu->name : null,
                    ],
                    'products_without_menu' => [
                        'ok' => $productsWithoutMenu === 0,
                        'count' => $productsWithoutMenu,
                    ],
                    'products_without_default_price' => [
                        'ok' => $productsWithoutDefaultPrice === 0,
                        'count' => $productsWithoutDefaultPrice,
                    ],
                ],
            ],
        ]);
    }
}
