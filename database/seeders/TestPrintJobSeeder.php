<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Orders\Domain\Entities\Order;
use Modules\Printers\Domain\Entities\Printer;
use Modules\Printers\Domain\Entities\PrintJob;
use Modules\Printers\Domain\Services\KitchenCommandFormatter;

class TestPrintJobSeeder extends Seeder
{
    public function run(): void
    {
        // Buscar impresora de cocina
        $printer = Printer::withoutGlobalScopes()
            ->where('name', 'Impresora Cocina Test')
            ->where('is_active', true)
            ->first();

        if (!$printer) {
            $this->command->error("❌ No se encontró 'Impresora Cocina Test'");
            return;
        }

        // Buscar pedido con items
        $order = Order::withoutGlobalScopes()
            ->where('status', 'confirmed')
            ->with(['items'])
            ->latest()
            ->first();

        if (!$order || $order->items->isEmpty()) {
            $this->command->error("❌ No hay pedidos confirmed con items");
            return;
        }

        $this->command->info("📦 Creando PrintJob DIRECTAMENTE (sin listener)...");
        $this->command->info("   Order: {$order->uuid}");
        $this->command->info("   Items: " . $order->items->count());
        $this->command->info("   Printer: {$printer->name}");

        // Preparar datos
        $items = $order->items->map(fn($item) => [
            'name' => $item->name_snapshot ?? 'Producto',
            'qty' => $item->quantity,
            'notes' => $item->notes ?? '',
        ])->toArray();

        $commandData = [
            'order_number' => $order->order_number,
            'table' => 'Mesa ?',
            'waiter' => 'Mesero Test',
            'items' => $items,
            'timestamp' => now()->format('H:i'),
        ];

        // Generar bytes
        $formatter = new KitchenCommandFormatter();
        $bytes = $formatter->format($commandData);

        // Crear PrintJob directamente
        $job = PrintJob::create([
            'company_id' => $order->company_id,
            'branch_id' => $order->branch_id,
            'printer_id' => $printer->id,
            'job_type' => PrintJob::TYPE_KITCHEN_COMMAND,
            'order_id' => $order->id,
            'escpos_bytes' => $bytes,
            'status' => PrintJob::STATUS_PENDING,
        ]);

        $this->command->info("✅ PrintJob creado: {$job->uuid}");
        $this->command->info("   Bytes: " . strlen($bytes));
    }
}
