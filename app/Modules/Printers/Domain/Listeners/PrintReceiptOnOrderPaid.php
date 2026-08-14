<?php

namespace Modules\Printers\Domain\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Orders\Domain\Events\OrderPaid;
use Modules\Printers\Domain\Entities\Printer;
use Modules\Printers\Domain\Entities\PrintJob;
use Modules\Printers\Domain\Services\ReceiptFormatter;
use Modules\Printers\Domain\ValueObjects\PrinterType;

/**
 * Listener que imprime ticket de cliente cuando un pedido es pagado.
 */
class PrintReceiptOnOrderPaid
{
    public function __construct(
        private ReceiptFormatter $formatter = new ReceiptFormatter()
    ) {}

    public function handle(OrderPaid $event): void
    {
        $order = $event->order;
        $order->load(['items', 'company', 'branch', 'waiter']);

        // Buscar impresora de recibos de la sucursal
        $receiptPrinter = Printer::where('company_id', $order->company_id)
            ->where('branch_id', $order->branch_id)
            ->where('type', PrinterType::RECEIPT)
            ->where('is_active', true)
            ->first();

        if (!$receiptPrinter) {
            Log::info('No hay impresora de recibos configurada', [
                'order_id' => $order->id,
                'branch_id' => $order->branch_id,
            ]);
            return;
        }

        // Datos del ticket
        $ticketData = [
            'company_name' => $order->company?->trade_name ?? 'Restaurant',
            'branch_name' => $order->branch?->name ?? 'Sucursal',
            'order_number' => $order->order_number,
            'date' => now()->format('d/m/Y H:i'),
            'waiter' => $order->waiter?->name ?? 'N/A',
            'items' => $order->items->map(function ($item) {
                return [
                    'name' => $item->name_snapshot,
                    'qty' => $item->quantity,
                    'price' => (float) $item->unit_price_snapshot,
                    'subtotal' => (float) $item->subtotal,
                ];
            })->toArray(),
            'subtotal' => (float) $order->subtotal,
            'tax' => (float) $order->tax_amount,
            'discount' => (float) $order->discount_amount,
            'total' => (float) $order->total,
            'payment_method' => 'Efectivo', // TODO: obtener del payment real
            'barcode' => $order->order_number,
        ];

        $bytes = $this->formatter->format($ticketData);

        // Agregar comando de apertura de cajón si está configurado
        if ($receiptPrinter->open_drawer_on_print) {
            $escPos = new \Modules\Printers\Domain\Services\EscPosService();
            $bytes = $escPos->openDrawer() . $bytes;
        }

        PrintJob::create([
            'company_id' => $order->company_id,
            'branch_id' => $order->branch_id,
            'printer_id' => $receiptPrinter->id,
            'job_type' => PrintJob::TYPE_RECEIPT,
            'order_id' => $order->id,
            'escpos_bytes' => $bytes,
            'status' => PrintJob::STATUS_PENDING,
        ]);

        Log::info('PrintJob de ticket creado', [
            'order_id' => $order->id,
            'printer' => $receiptPrinter->name,
            'bytes' => strlen($bytes),
        ]);
    }
}
