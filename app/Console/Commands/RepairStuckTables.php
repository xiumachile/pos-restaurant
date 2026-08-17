<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Repara mesas y pedidos atascados usando solo DB directo
 * para evitar problemas con scopes de tenant context.
 *
 * Uso:
 *   php artisan tables:repair-stuck              # Aplicar reparación
 *   php artisan tables:repair-stuck --dry-run    # Solo preview
 */
class RepairStuckTables extends Command
{
    protected $signature = 'tables:repair-stuck {--dry-run : Solo mostrar qué se repararía, sin escribir}';

    protected $description = 'Repara pedidos atascados en paid y mesas atascadas en occupied/billing';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info($dryRun ? '=== DRY RUN — no se escribe nada ===' : '=== APLICANDO REPARACIÓN ===');
        $this->newLine();

        // -----------------------------------------------------------------
        // Paso 1: avanzar pedidos 'paid' → 'closed'
        // -----------------------------------------------------------------
        $this->info('Paso 1: Detectar pedidos stuck en status "paid"...');

        $stuckOrders = DB::table('orders')
            ->where('status', 'paid')
            ->get(['id', 'order_number', 'table_id', 'total']);

        $this->info("  Pedidos en 'paid' sin cerrar: {$stuckOrders->count()}");

        foreach ($stuckOrders as $order) {
            $tableName = DB::table('restaurant_tables')
                ->where('id', $order->table_id)
                ->value('table_number') ?? '?';

            $this->line("    - {$order->order_number} (mesa={$tableName}, total=\${$order->total})");

            if ($dryRun) {
                continue;
            }

            try {
                DB::table('orders')
                    ->where('id', $order->id)
                    ->update([
                        'status' => 'closed',
                        'closed_at' => now(),
                        'updated_at' => now(),
                    ]);

                Log::info('RepairStuckTables: pedido cerrado', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ]);
            } catch (\Throwable $e) {
                $this->error("      ERROR: {$e->getMessage()}");
                Log::error('RepairStuckTables: error al cerrar pedido', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();

        // -----------------------------------------------------------------
        // Paso 2: liberar mesas stuck (occupied/billing sin pedidos activos)
        // -----------------------------------------------------------------
        $this->info('Paso 2: Detectar mesas stuck sin pedidos activos...');

        $stuckTables = DB::table('restaurant_tables')
            ->whereIn('status', ['occupied', 'billing'])
            ->get(['id', 'table_number', 'status']);

        $tablesToFree = [];
        foreach ($stuckTables as $table) {
            $activeOrdersCount = DB::table('orders')
                ->where('table_id', $table->id)
                ->whereIn('status', ['draft', 'confirmed', 'preparing', 'ready', 'served'])
                ->count();

            if ($activeOrdersCount === 0) {
                $tablesToFree[] = $table;
            }
        }

        $this->info("  Mesas stuck sin pedidos activos: " . count($tablesToFree));

        foreach ($tablesToFree as $table) {
            $this->line("    - Mesa {$table->table_number} (status actual: {$table->status})");

            if ($dryRun) {
                continue;
            }

            try {
                DB::table('restaurant_tables')
                    ->where('id', $table->id)
                    ->update([
                        'status' => 'available',
                        'updated_at' => now(),
                    ]);

                Log::info('RepairStuckTables: mesa liberada', [
                    'table_id' => $table->id,
                    'table_number' => $table->table_number,
                    'previous_status' => $table->status,
                ]);
            } catch (\Throwable $e) {
                $this->error("      ERROR: {$e->getMessage()}");
                Log::error('RepairStuckTables: error al liberar mesa', [
                    'table_id' => $table->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();

        // -----------------------------------------------------------------
        // Resumen
        // -----------------------------------------------------------------
        if (!$dryRun) {
            $this->info('=== RESUMEN DE REPARACIÓN ===');
            $this->line("  Pedidos cerrados: {$stuckOrders->count()}");
            $this->line("  Mesas liberadas: " . count($tablesToFree));
            $this->newLine();
        }

        $this->info($dryRun
            ? '✅ Dry run completo. Corre sin --dry-run para aplicar los cambios.'
            : '✅ Reparación completa.');

        return self::SUCCESS;
    }
}
