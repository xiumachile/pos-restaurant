<?php

namespace Modules\Sync\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Domain\Contracts\CatalogExportServiceInterface;
use Modules\Payments\Domain\Contracts\PaymentsExportServiceInterface;
use Modules\Tables\Domain\Contracts\TablesExportServiceInterface;

class SyncIncrementalController extends Controller
{
    public function __construct(
        private CatalogExportServiceInterface $catalogExport,
        private TablesExportServiceInterface $tablesExport,
        private PaymentsExportServiceInterface $paymentsExport
    ) {
    }

    public function pull(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;
        $since = $request->input('since') ? Carbon::parse($request->input('since')) : null;

        return response()->json([
            'categories' => $this->catalogExport->getChangedCategories($branchId, $since),
            'products' => $this->catalogExport->getChangedProducts($branchId, $since),
            'tables' => $this->tablesExport->getChangedTables($branchId, $since),
            'payment_methods' => $this->paymentsExport->getChangedPaymentMethods($branchId, $since),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    private function getChangedCategories(int $branchId, ?Carbon $since): array
    {
        return $this->catalogExport->getChangedCategories($branchId, $since)->toArray();
    }

    private function getChangedProducts(int $branchId, ?Carbon $since): array
    {
        return $this->catalogExport->getChangedProducts($branchId, $since)->toArray();
    }

    private function getChangedTables(int $branchId, ?Carbon $since): array
    {
        return $this->tablesExport->getChangedTables($branchId, $since)->toArray();
    }

    private function getChangedPaymentMethods(int $branchId, ?Carbon $since): array
    {
        return $this->paymentsExport->getChangedPaymentMethods($branchId, $since)->toArray();
    }
}
