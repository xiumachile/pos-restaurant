<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Actualiza el CHECK constraint de la columna status en la tabla orders
 * para incluir los 4 nuevos estados de fulfillment (Fase 4).
 *
 * NUEVOS ESTADOS AGREGADOS:
 * - ready_for_pickup: takeout listo para retirar
 * - picked_up: cliente retiró el pedido (takeout)
 * - dispatched: pedido salió a entrega (delivery)
 * - delivered: pedido entregado al cliente (delivery)
 *
 * ESTADOS EXISTENTES (mantenidos):
 * - draft, confirmed, preparing, ready, served, paid, closed, cancelled
 */
return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL: DROP + ADD constraint
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check');
        
        DB::statement("
            ALTER TABLE orders 
            ADD CONSTRAINT orders_status_check 
            CHECK (status IN (
                'draft',
                'confirmed',
                'preparing',
                'ready',
                'ready_for_pickup',
                'picked_up',
                'dispatched',
                'delivered',
                'served',
                'paid',
                'closed',
                'cancelled'
            ))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check');
        
        DB::statement("
            ALTER TABLE orders 
            ADD CONSTRAINT orders_status_check 
            CHECK (status IN (
                'draft',
                'confirmed',
                'preparing',
                'ready',
                'served',
                'paid',
                'closed',
                'cancelled'
            ))
        ");
    }
};
