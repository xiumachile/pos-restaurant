<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Recipes\Domain\Entities\RawIngredient;
use Modules\Recipes\Domain\Entities\RawIngredientMovement;
use Modules\Recipes\Domain\ValueObjects\MovementType;

/**
 * Seeder de movimientos de prueba para desarrollo.
 * Crea movimientos históricos para los insumos existentes.
 */
class RawIngredientMovementSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = 1;
        $branchId = 1;

        // Verificar si ya existen movimientos
        if (RawIngredientMovement::withoutGlobalScopes()->where('company_id', $companyId)->count() > 0) {
            $this->command->info('Ya existen movimientos, saltando seeder.');
            return;
        }

        $ingredients = RawIngredient::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->limit(5)
            ->get();

        if ($ingredients->isEmpty()) {
            $this->command->warn('No hay insumos, ejecuta primero RawIngredientSeeder.');
            return;
        }

        $movementsCreated = 0;

        foreach ($ingredients as $ingredient) {
            // Movimiento de compra inicial
            RawIngredientMovement::record(
                companyId: $companyId,
                branchId: $branchId,
                rawIngredientId: $ingredient->id,
                type: MovementType::InPurchase,
                quantityBase: $ingredient->current_stock_base + 5000,
                referenceType: 'purchase',
                referenceId: null,
                reason: 'Compra inicial de stock'
            );
            $movementsCreated++;

            // Movimiento de consumo (simula un pedido)
            if ($ingredient->current_stock_base > 1000) {
                RawIngredientMovement::record(
                    companyId: $companyId,
                    branchId: $branchId,
                    rawIngredientId: $ingredient->id,
                    type: MovementType::OutConsumption,
                    quantityBase: 1000,
                    referenceType: 'order',
                    referenceId: 1,
                    reason: 'Consumo por pedido #1'
                );
                $movementsCreated++;
            }

            // Movimiento de ajuste (inventario físico)
            if ($ingredient->current_stock_base > 500) {
                RawIngredientMovement::record(
                    companyId: $companyId,
                    branchId: $branchId,
                    rawIngredientId: $ingredient->id,
                    type: MovementType::Adjustment,
                    quantityBase: -500,
                    referenceType: null,
                    referenceId: null,
                    reason: 'Ajuste por inventario físico'
                );
                $movementsCreated++;
            }
        }

        $this->command->info("✅ {$movementsCreated} movimientos creados exitosamente.");
    }
}
