<?php

namespace Modules\Payments\Domain\Contracts;

use Carbon\Carbon;
use Illuminate\Support\Collection;

interface PaymentsExportServiceInterface
{
    public function getChangedPaymentMethods(int $branchId, ?Carbon $since): Collection;
}
