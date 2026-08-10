<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Tables\Domain\Entities\RestaurantTable;

class TablesDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Obtener todas las sucursales activas (no depender de un tax_id específico)
        $branches = Branch::where('is_active', true)->get();

        if ($branches->isEmpty()) {
            $this->command->warn('⚠️  No hay sucursales activas. Ejecuta BaseTenantSeeder primero.');
            return;
        }

        foreach ($branches as $branch) {
            $this->seedBranch($branch);
        }

        $this->command->info('');
        $this->command->info('=== RESUMEN MESAS DEMO ===');
        $this->command->info('Mesas totales: ' . RestaurantTable::count());
        $this->command->info('Por sucursal:');

        foreach ($branches as $branch) {
            $count = RestaurantTable::where('branch_id', $branch->id)->count();
            $this->command->info("  - {$branch->name}: {$count} mesas");
        }
    }

    private function seedBranch(Branch $branch): void
    {
        // ============================================
        // Configuración de áreas por sucursal
        // ============================================
        $areas = [
            [
                'code' => 'MAIN',
                'es' => 'Salón Principal',
                'zh' => '主厅',
                'tables' => [
                    ['number' => 'M-01', 'capacity' => 4],
                    ['number' => 'M-02', 'capacity' => 4],
                    ['number' => 'M-03', 'capacity' => 6],
                    ['number' => 'M-04', 'capacity' => 2],
                    ['number' => 'M-05', 'capacity' => 8],
                ],
            ],
            [
                'code' => 'TERRAZA',
                'es' => 'Terraza',
                'zh' => '露台',
                'tables' => [
                    ['number' => 'T-01', 'capacity' => 2],
                    ['number' => 'T-02', 'capacity' => 2],
                    ['number' => 'T-03', 'capacity' => 4],
                    ['number' => 'T-04', 'capacity' => 4],
                ],
            ],
            [
                'code' => 'VIP',
                'es' => 'Área VIP',
                'zh' => '贵宾区',
                'tables' => [
                    ['number' => 'VIP-01', 'capacity' => 8],
                    ['number' => 'VIP-02', 'capacity' => 12],
                ],
            ],
            [
                'code' => 'BAR',
                'es' => 'Barra',
                'zh' => '吧台',
                'tables' => [
                    ['number' => 'B-01', 'capacity' => 2],
                    ['number' => 'B-02', 'capacity' => 2],
                    ['number' => 'B-03', 'capacity' => 2],
                ],
            ],
        ];

        foreach ($areas as $area) {
            foreach ($area['tables'] as $table) {
                RestaurantTable::updateOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'table_number' => $table['number'],
                    ],
                    [
                        'company_id' => $branch->company_id,
                        'area_code' => $area['code'],
                        'area_name_translations' => [
                            'es' => $area['es'],
                            'zh' => $area['zh'],
                        ],
                        'capacity' => $table['capacity'],
                        'status' => 'available',
                    ]
                );
            }
        }

        $this->command->info("  ✅ " . count($areas) . " áreas creadas para {$branch->name}");
    }
}
