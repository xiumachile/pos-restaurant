<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Event;
use Modules\Kitchen\Domain\Events\BroadcastOrderCancelled;
use Modules\Kitchen\Domain\Events\BroadcastOrderConfirmed;
use Modules\Kitchen\Domain\Events\BroadcastOrderPaid;
use Modules\Kitchen\Domain\Events\BroadcastOrderReady;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Los tests de negocio no deben depender de un servicio externo
        // de broadcasting real. Solo se simulan los eventos que EXISTEN
        // exclusivamente para emitir al canal de Kitchen — no tocan
        // ninguna otra lógica de negocio (inventario, auditoría, etc.
        // siguen escuchando OrderConfirmed/OrderPaid/etc. normalmente,
        // porque esos son eventos de dominio distintos, no estos).
        Event::fake([
            BroadcastOrderConfirmed::class,
            BroadcastOrderReady::class,
            BroadcastOrderCancelled::class,
            BroadcastOrderPaid::class,
        ]);
    }
}
