<?php

namespace App\Shared\Domain\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Orders\Domain\Entities\Order;

/**
 * Job de ejemplo que procesa una orden.
 * 
 * REQUIERE contexto de tenant establecido antes de ejecutarse.
 * Si el contexto no está configurado, lanzará TenantContextNotSetException
 * al intentar consultar el modelo Order.
 */
class ProcessOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $orderId
    ) {}

    public function handle(): void
    {
        // Esta consulta fallará si no hay contexto de tenant
        // porque Order usa el trait BelongsToTenant con CompanyScope
        $order = Order::findOrFail($this->orderId);
        
        // Procesar la orden...
        \Log::info("Procesando orden {$order->id} de empresa {$order->company_id}");
    }
}
