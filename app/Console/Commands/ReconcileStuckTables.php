<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Tables\Domain\ValueObjects\TableStatus;
use Modules\Orders\Domain\Entities\Order;

/**
 * Libera mesas que quedaron atascadas en estado "occupied"
 * debido a que sus pedidos ya fueron pagados/cerrados pero
 * el listener ReleaseTableOnOrderPaid no se ejecutó correctamente.
 * 
 * Casos típicos:
 * - Pedidos sincronizados desde offline (antes del fix de SyncEngine)
 * - Pedidos creados antes del fix donde OrderConfirmed no se disparó
 * 
 * Uso:
 *   php artisan tables:reconcile-stuck --dry-run  # Ver qué haría
 *   php artisan tables:reconcile-stuck            # Ejecutar cambios
 */
class ReconcileStuckTables extends Command
{
    protected $signature = 'tables:reconcile-stuck {--dry-run : Solo mostrar, no ejecutar cambios}';
    protected $description = 'Liberar mesas atascadas en occupied con pedidos ya pagados/cerrados';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  Reconciliación de mesas atascadas');
        $this->info('═══════════════════════════════════════════════════════');
        
        // Buscar mesas en occupied
        $stuckTables = RestaurantTable::withoutGlobalScopes()
            ->where('status', TableStatus::Occupied)
            ->whereNotNull('current_order_id')
            ->get();
        
        $this->info("Mesas en 'occupied' con current_order_id: {$stuckTables->count()}");
        
        if ($stuckTables->count() === 0) {
            $this->info('✅ No hay mesas atascadas');
            return Command::SUCCESS;
        }
        
        $releasedCount = 0;
        
        foreach ($stuckTables as $table) {
            $order = Order::withoutGlobalScopes()->find($table->current_order_id);
            
            if (!$order) {
                $this->warn("  ⚠️  Mesa {$table->table_number}: pedido ID {$table->current_order_id} NO EXISTE");
                if (!$dryRun) {
                    $table->current_order_id = null;
                    $table->status = TableStatus::Available;
                    $table->save();
                    $releasedCount++;
                    $this->info("     ✅ Liberada");
                }
                continue;
            }
            
            if (in_array($order->status->value, ['paid', 'closed', 'cancelled'])) {
                $this->info("  🔧 Mesa {$table->table_number}: pedido {$order->order_number} está '{$order->status->value}'");
                if (!$dryRun) {
                    $table->current_order_id = null;
                    $table->status = TableStatus::Available;
                    $table->save();
                    $releasedCount++;
                    $this->info("     ✅ Liberada");
                } else {
                    $this->info("     (dry-run: no se modifica)");
                }
            } else {
                $this->info("  ✓ Mesa {$table->table_number}: pedido {$order->order_number} activo ({$order->status->value})");
            }
        }
        
        $this->info('═══════════════════════════════════════════════════════');
        if ($dryRun) {
            $this->info("Dry-run completado. Ejecutar sin --dry-run para aplicar cambios.");
        } else {
            $this->info("Mesas liberadas: {$releasedCount}");
        }
        $this->info('═══════════════════════════════════════════════════════');
        
        return Command::SUCCESS;
    }
}
