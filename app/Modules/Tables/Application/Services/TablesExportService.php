<?php

namespace Modules\Tables\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Tables\Domain\Contracts\TablesExportServiceInterface;

class TablesExportService implements TablesExportServiceInterface
{
    public function getChangedTables(int $branchId, ?Carbon $since): Collection
    {
        $query = DB::table('restaurant_tables')
            ->where('branch_id', $branchId);

        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        return $query->get()->map(function ($table) {
            return [
                'uuid' => $table->uuid,
                'table_number' => $table->table_number,
                'capacity' => $table->capacity,
                'area_code' => $table->area_code,
                'area_name_translations' => json_decode($table->area_name_translations, true),
                'status' => $table->status,
                'is_active' => $table->is_active ?? true,
                'updated_at' => $table->updated_at,
            ];
        });
    }
}
