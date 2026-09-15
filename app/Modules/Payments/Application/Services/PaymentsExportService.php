<?php

namespace Modules\Payments\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Payments\Domain\Contracts\PaymentsExportServiceInterface;

class PaymentsExportService implements PaymentsExportServiceInterface
{
    public function getChangedPaymentMethods(int $branchId, ?Carbon $since): Collection
    {
        $companyId = auth()->user()->company_id;
        
        $query = DB::table('payment_methods')
            ->where('company_id', $companyId)  // FIX P0: Filtrar por company_id
            ->where('branch_id', $branchId);

        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        return $query->get()->map(function ($method) {
            return [
                'uuid' => $method->uuid,
                'code' => $method->code,
                'name_translations' => json_decode($method->name_translations, true),
                'type' => $method->type,
                'requires_reference' => $method->requires_reference,
                'is_active' => $method->is_active,
                'sort_order' => $method->sort_order,
                'updated_at' => $method->updated_at,
            ];
        });
    }
}
