<?php

namespace Modules\Recipes\Domain\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Orders\Domain\Events\OrderCancelled;
use Modules\Recipes\Domain\Entities\RawIngredientMovement;
use Modules\Recipes\Domain\ValueObjects\MovementType;

/**
 * I-01 FIX: Listener que devuelve automáticamente los insumos de las recetas
 * cuando un pedido es cancelado (siempre que hayan sido descontados previamente).
 * 
 * Genera movimientos de compensación (Adjustment positivo) auditables.
 * UX Bilingüe: Todos los logs y mensajes en español (ES) y chino (ZH).
 */
class ReturnIngredientsOnOrderCancel
{
    public function handle(OrderCancelled $event): void
    {
        $order = $event->order;
        
        Log::info('[ES] ReturnIngredientsOnOrderCancel: Listener ejecutado / [ZH] 退料监听器已执行', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'branch_id' => $order->branch_id,
            'company_id' => $order->company_id,
            'timestamp' => now()->toIso8601String(),
        ]);
        
        try {
            DB::transaction(function () use ($order) {
                // Buscar todos los movimientos de consumo de esta orden
                $consumptionMovements = RawIngredientMovement::where('company_id', $order->company_id)
                    ->where('branch_id', $order->branch_id)
                    ->where('reference_type', 'order')
                    ->where('reference_id', $order->id)
                    ->where('type', MovementType::OutConsumption)
                    ->get();

                if ($consumptionMovements->isEmpty()) {
                    Log::info('[ES] No hay movimientos de consumo para devolver / [ZH] 无消费记录可退回', [
                        'order_id' => $order->id,
                    ]);
                    return;
                }

                foreach ($consumptionMovements as $movement) {
                    // Generar idempotency_key para el movimiento de compensación
                    $compensationKey = sprintf(
                        'RECIPE-CANCEL-%s-%d',
                        $movement->order_item_uuid ?? $movement->id,
                        $movement->raw_ingredient_id
                    );

                    // Registrar movimiento de compensación (Adjustment positivo)
                    RawIngredientMovement::record(
                        companyId: $movement->company_id,
                        branchId: $movement->branch_id,
                        rawIngredientId: $movement->raw_ingredient_id,
                        type: MovementType::Adjustment,
                        quantityBase: abs((float) $movement->quantity_base),
                        referenceType: 'order_cancellation',
                        referenceId: $order->id,
                        userId: null,
                        reason: "[ES] Devolución por cancelación de pedido #{$order->order_number} / [ZH] 订单 #{$order->order_number} 取消退料",
                        idempotencyKey: $compensationKey,
                        orderItemUuid: $movement->order_item_uuid,
                        recipeVersion: $movement->recipe_version
                    );

                    Log::info('[ES] Insumo devuelto exitosamente / [ZH] 原料退料成功', [
                        'order_id' => $order->id,
                        'ingredient_id' => $movement->raw_ingredient_id,
                        'quantity_returned' => abs((float) $movement->quantity_base),
                        'compensation_key' => $compensationKey,
                    ]);
                }
            });

            Log::info('[ES] Devolución de insumos completada / [ZH] 退料流程完成', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
        } catch (\Exception $e) {
            Log::error('[ES] Error al devolver insumos de receta / [ZH] 退料失败', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
