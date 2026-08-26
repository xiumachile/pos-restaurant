<?php

namespace Modules\Tables\Domain\Contracts;

use Carbon\Carbon;
use Illuminate\Support\Collection;

interface TablesExportServiceInterface
{
    public function getChangedTables(int $branchId, ?Carbon $since): Collection;
}
