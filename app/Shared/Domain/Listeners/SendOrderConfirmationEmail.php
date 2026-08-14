<?php

namespace App\Shared\Domain\Listeners;

use App\Shared\Domain\Events\OrderCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Orders\Domain\Entities\Order;

/**
 * Listener que envía email de confirmación.
 * 
 * Si se ejecuta asíncronamente (ShouldQueue), DEBE recibir el contexto
 * de tenant en el payload del job, o fallará con TenantContextNotSetException.
 */
class SendOrderConfirmationEmail implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(OrderCreated $event): void
    {
        $order = $event->order;
        
        // Esta consulta podría fallar si el listener se ejecuta en cola
        // sin contexto de tenant y el order fue cargado sin eager loading
        $order->load('items.product');
        
        \Log::info("Enviando email para orden {$order->id}");
        
        // Enviar email...
    }
}
