<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Services\OrderStateMachine;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Tables\Domain\ValueObjects\TableStatus;

/**
 * Repara mesas y pedidos que quedaron atascados por el bug de
 * chargeTable() previo al fix (pedidos en 'paid' que nunca llegaron a
 * 'closed', y mesas en 'occupied'/'billing' que nunca se liberaron).
 *
 * IMPORTANTE: correr esto DESPUÉS de desplegar el fix de
 * UpdateTableOnPaid / UpdateTableOnClose / CashierTablesController.
 * Si se corre antes, los pedidos avanzarán a 'closed' pero las mesas
 * seguirán sin liberarse, porque el listener viejo seguiría con el bug.
 */
class RepairStuckTables extends Command
{
    protected $signature = 'tables:repair-stuck {--dry-run : Solo mostrar qué se repararía, sin escribir}';

    protected $description = 'Repara pedidos atascados en paid y mesas atascadas en occupied/billing (bug pre-fix de chargeTable)';

    public function handle(OrderStateMachine $orderStateMachine): int
    {
        $dryRun = $this->option('dry-run');

        $this->info($dryRun ? '=== DRY RUN — no se escribe nada ===' : '=== APLICANDO REPARACIÓN ===');

        // -----------------------------------------------------------------
        // Paso 1: avanzar pedidos 'paid' huérfanos (nunca llegaron a closed)
        // -----------------------------------------------------------------
        $stuckOrders = Order::where('status', OrderStatus::PAID)->get();

        $this->info("Pedidos en 'paid' sin cerrar: {$stuckOrders->count()}");

        foreach ($stuckOrders as $order) {
            $this->line("  - {$order->order_number} (mesa_id={$order->table_id}, total={$order->total})");

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($order, $orderStateMachine) {
                try {
                    $orderStateMachine->transition($order, OrderStatus::CLOSED);
                } catch (\Throwable $e) {
                    Log::error('RepairStuckTables: no se pudo cerrar pedido', [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'error' => $e->getMessage(),
                    ]);
                    $this->error("    ERROR: {$e->getMessage()}");
                }
            });
        }

        // -----------------------------------------------------------------
        // Paso 2: liberar mesas que sigan atascadas después del paso 1
        // (cubre también mesas con status=billing/occupied que no tienen
        // NINGÚN pedido asociado, como Mesa 5 y B4 en el caso reportado)
        // -----------------------------------------------------------------
        $stuckTables = RestaurantTable::whereIn('status', [TableStatus::Occupied, TableStatus::Billing])
            ->get()
            ->filter(function (RestaurantTable $table) {
                $hasActiveOrders = Order::where('table_id', $table->id)
                    ->whereIn('status', [
                        OrderStatus::CONFIRMED,
                        OrderStatus::PREPARING,
                        OrderStatus::READY,
                        OrderStatus::SERVED,
                    ])
                    ->exists();

                return !$hasActiveOrders;
            });

        $this->info("Mesas atascadas sin pedidos activos: {$stuckTables->count()}");

        foreach ($stuckTables as $table) {
            $this->line("  - Mesa {$table->table_number} (status actual: {$table->status->value})");

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($table) {
                try {
                    if ($table->status === TableStatus::Occupied) {
                        $table->requestBilling();
                    }
                    $table->free();
                    $table->save();

                    Log::info('RepairStuckTables: mesa liberada', [
                        'table_id' => $table->id,
                        'table_number' => $table->table_number,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('RepairStuckTables: no se pudo liberar mesa', [
                        'table_id' => $table->id,
                        'table_number' => $table->table_number,
                        'error' => $e->getMessage(),
                    ]);
                    $this->error("    ERROR: {$e->getMessage()}");
                }
            });
        }

        $this->info($dryRun
            ? 'Dry run completo. Corre sin --dry-run para aplicar.'
            : 'Reparación completa.');

        return self::SUCCESS;
    }
}
