<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;

class TablesSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::withoutGlobalScopes()
            ->where('trade_name', 'Wok & Mesa')
            ->first();

        if (!$company) {
            $this->command->error('Compañía Wok & Mesa no existe.');
            return;
        }

        $branch = Branch::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('code', 'MAIN')
            ->first();

        if (!$branch) {
            $this->command->error('Branch MAIN no existe.');
            return;
        }

        $existing = RestaurantTable::withoutGlobalScopes()
            ->where('branch_id', $branch->id)
            ->count();

        if ($existing > 0) {
            $this->command->info("Ya existen {$existing} mesas. Saltando.");
            return;
        }

        // Áreas del restaurante (definidas por area_code)
        $areas = [
            'MAIN' => ['es' => 'Salón Principal', 'zh' => '主厅'],
            'BAR' => ['es' => 'Barra', 'zh' => '吧台'],
            'TERRACE' => ['es' => 'Terraza', 'zh' => '露台'],
        ];

        // Mesas distribuidas en áreas con distintos estados
        $tables = [
            // Salón Principal
            ['table_number' => '1', 'capacity' => 2, 'status' => 'available', 'area_code' => 'MAIN'],
            ['table_number' => '2', 'capacity' => 4, 'status' => 'available', 'area_code' => 'MAIN'],
            ['table_number' => '3', 'capacity' => 4, 'status' => 'occupied', 'area_code' => 'MAIN'],
            ['table_number' => '4', 'capacity' => 6, 'status' => 'available', 'area_code' => 'MAIN'],
            ['table_number' => '5', 'capacity' => 2, 'status' => 'billing', 'area_code' => 'MAIN'],
            ['table_number' => '6', 'capacity' => 8, 'status' => 'available', 'area_code' => 'MAIN'],
            ['table_number' => '7', 'capacity' => 4, 'status' => 'occupied', 'area_code' => 'MAIN'],
            ['table_number' => '8', 'capacity' => 4, 'status' => 'available', 'area_code' => 'MAIN'],
            ['table_number' => '9', 'capacity' => 2, 'status' => 'maintenance', 'area_code' => 'MAIN'],
            ['table_number' => '10', 'capacity' => 6, 'status' => 'available', 'area_code' => 'MAIN'],
            // Barra
            ['table_number' => 'B1', 'capacity' => 2, 'status' => 'available', 'area_code' => 'BAR'],
            ['table_number' => 'B2', 'capacity' => 2, 'status' => 'occupied', 'area_code' => 'BAR'],
            ['table_number' => 'B3', 'capacity' => 2, 'status' => 'available', 'area_code' => 'BAR'],
            ['table_number' => 'B4', 'capacity' => 2, 'status' => 'billing', 'area_code' => 'BAR'],
            // Terraza
            ['table_number' => 'T1', 'capacity' => 4, 'status' => 'available', 'area_code' => 'TERRACE'],
            ['table_number' => 'T2', 'capacity' => 4, 'status' => 'available', 'area_code' => 'TERRACE'],
            ['table_number' => 'T3', 'capacity' => 6, 'status' => 'occupied', 'area_code' => 'TERRACE'],
            ['table_number' => 'T4', 'capacity' => 6, 'status' => 'available', 'area_code' => 'TERRACE'],
        ];

        foreach ($tables as $tableData) {
            $areaCode = $tableData['area_code'];
            $table = RestaurantTable::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'table_number' => $tableData['table_number'],
                'capacity' => $tableData['capacity'],
                'status' => $tableData['status'],
                'area_code' => $areaCode,
                'area_name_translations' => $areas[$areaCode],
            ]);
            $this->command->info("Mesa {$table->table_number} ({$areaCode}) - {$table->status->value}, {$table->capacity}p");
        }

        $this->command->info('');
        $this->command->info(count($tables) . ' mesas creadas en 3 áreas.');
    }
}
