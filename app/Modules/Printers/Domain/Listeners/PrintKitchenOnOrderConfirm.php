<?php

namespace Modules\Printers\Domain\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Orders\Domain\Events\OrderConfirmed;
use Modules\Printers\Domain\Entities\Printer;
use Modules\Printers\Domain\Entities\PrinterStationMapping;
use Modules\Printers\Domain\Entities\PrintJob;
use Modules\Printers\Domain\Services\KitchenCommandFormatter;
use Modules\Printers\Domain\ValueObjects\PrinterType;

/**
 * Listener que imprime comandas en cocina cuando un pedido es confirmado.
 * 
 * Flujo:
 * 1. Recibe evento OrderConfirmed
 * 2. Agrupa items por impresora según PrinterStationMapping
 * 3. Genera bytes ESC/POS con KitchenCommandFormatter
 * 4. Crea PrintJob en estado 'pending' para cada impresora
 */
class PrintKitchenOnOrderConfirm
{
    public function __construct(
        private KitchenCommandFormatter $formatter = new KitchenCommandFormatter()
    ) {}

    public function handle(OrderConfirmed $event): void
    {
        $order = $event->order;
        $order->load(['items.product', 'items.product.category']);
        
        $branchId = $order->branch_id;
        $companyId = $order->company_id;

        Log::info('PrintKitchenOnOrderConfirm: procesando pedido', [
            'order_id' => $order->id,
            'items_count' => $order->items->count(),
        ]);

        // Obtener todas las impresoras de cocina activas para esta sucursal
        $kitchenPrinters = Printer::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereIn('type', [PrinterType::KITCHEN, PrinterType::BAR])
            ->get();

        if ($kitchenPrinters->isEmpty()) {
            Log::info('No hay impresoras de cocina configuradas', [
                'branch_id' => $branchId,
            ]);
            return;
        }

        // Obtener mappings de esta sucursal
        $mappings = PrinterStationMapping::where('branch_id', $branchId)
            ->where('is_active', true)
            ->with('printer')
            ->get();

        // Agrupar items por impresora
        $itemsByPrinter = $this->groupItemsByPrinter($order, $mappings, $kitchenPrinters);

        // Crear PrintJob para cada impresora
        foreach ($itemsByPrinter as $printerId => $items) {
            if (empty($items)) {
                continue;
            }

            $printer = $kitchenPrinters->firstWhere('id', $printerId);
            if (!$printer) {
                continue;
            }

            // Datos para la comanda
            $commandData = [
                'order_number' => $order->order_number,
                'table' => 'Mesa ' . ($order->table_id ?? '?'),
                'waiter' => $order->waiter?->name ?? 'N/A',
                'items' => $items,
                'timestamp' => now()->format('H:i'),
            ];

            // Usar formato compacto para bar, completo para cocina
            $bytes = $printer->type === PrinterType::BAR
                ? $this->formatter->formatCompact($commandData)
                : $this->formatter->format($commandData);

            PrintJob::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'printer_id' => $printerId,
                'job_type' => $printer->type === PrinterType::BAR
                    ? PrintJob::TYPE_BAR_COMMAND
                    : PrintJob::TYPE_KITCHEN_COMMAND,
                'order_id' => $order->id,
                'escpos_bytes' => $bytes,
                'status' => PrintJob::STATUS_PENDING,
            ]);

            Log::info('PrintJob creado', [
                'printer' => $printer->name,
                'items_count' => count($items),
                'bytes' => strlen($bytes),
            ]);
        }
    }

    /**
     * Agrupa los items del pedido por impresora según los mappings.
     */
    private function groupItemsByPrinter($order, $mappings, $kitchenPrinters): array
    {
        $itemsByPrinter = [];
        $defaultPrinter = $kitchenPrinters->firstWhere('type', PrinterType::KITCHEN);

        foreach ($order->items as $item) {
            $productName = $item->name_snapshot ?? $item->product?->name_translations['es'] ?? 'Producto';
            $categoryId = $item->product?->category_id;

            // Buscar mapping que coincida
            $matchedPrinterId = null;
            foreach ($mappings as $mapping) {
                if ($mapping->matchesProduct($productName, $categoryId)) {
                    $matchedPrinterId = $mapping->printer_id;
                    break;
                }
            }

            // Si no hay match, usar impresora default de cocina
            $targetPrinterId = $matchedPrinterId ?? $defaultPrinter?->id;
            if (!$targetPrinterId) {
                continue;
            }

            if (!isset($itemsByPrinter[$targetPrinterId])) {
                $itemsByPrinter[$targetPrinterId] = [];
            }

            $itemsByPrinter[$targetPrinterId][] = [
                'name' => $productName,
                'qty' => $item->quantity,
                'notes' => $item->notes ?? '',
            ];
        }

        return $itemsByPrinter;
    }
}
