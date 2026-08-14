<?php

namespace Modules\Printers\Domain\Services;

/**
 * Formateador de comandos de cocina.
 * Genera comandas optimizadas para impresoras de cocina (80mm, fuente grande).
 */
class KitchenCommandFormatter
{
    public function __construct(
        private EscPosService $escPos = new EscPosService()
    ) {}

    /**
     * Genera una comanda de cocina completa.
     *
     * @param array $data Datos de la comanda:
     *   - order_number: número de pedido
     *   - table: nombre/número de mesa
     *   - waiter: nombre del garzón
     *   - items: array de items [{name, qty, notes}]
     *   - timestamp: fecha/hora del pedido
     */
    public function format(array $data): string
    {
        $output = $this->escPos->initialize();
        
        // Encabezado con número de pedido (grande y centrado)
        $output .= $this->escPos->alignCenter($this->escPos->doubleSize("PEDIDO #" . $data['order_number']));
        $output .= $this->escPos->lineBreak();
        
        // Información de mesa y garzón
        $output .= $this->escPos->alignLeft($this->escPos->bold("Mesa: " . $data['table']));
        $output .= $this->escPos->alignLeft($this->escPos->bold("Garzón: " . $data['waiter']));
        $output .= $this->escPos->alignLeft("Hora: " . ($data['timestamp'] ?? now()->format('H:i')));
        
        $output .= $this->escPos->separator();
        
        // Lista de items
        foreach ($data['items'] as $item) {
            $qty = $item['qty'] ?? 1;
            $name = $item['name'] ?? 'Producto';
            $notes = $item['notes'] ?? '';
            
            // Cantidad y nombre (fuente grande)
            $output .= $this->escPos->alignLeft($this->escPos->doubleSize("{$qty}x {$name}"));
            
            // Notas especiales (si existen)
            if (!empty($notes)) {
                $output .= $this->escPos->alignLeft($this->escPos->bold("*** {$notes} ***"));
            }
            
            $output .= $this->escPos->lineBreak();
        }
        
        $output .= $this->escPos->separator();
        $output .= $this->escPos->cut();
        
        return $output;
    }

    /**
     * Genera una comanda compacta (para impresoras de bar).
     */
    public function formatCompact(array $data): string
    {
        $output = $this->escPos->initialize();
        
        // Encabezado compacto
        $output .= $this->escPos->alignCenter($this->escPos->bold("#{$data['order_number']} - {$data['table']}"));
        $output .= $this->escPos->separator(32);
        
        // Items compactos
        foreach ($data['items'] as $item) {
            $qty = $item['qty'] ?? 1;
            $name = $item['name'] ?? 'Producto';
            $output .= $this->escPos->alignLeft("{$qty}x {$name}");
            
            if (!empty($item['notes'])) {
                $output .= $this->escPos->alignLeft("   * {$item['notes']}");
            }
        }
        
        $output .= $this->escPos->cut();
        
        return $output;
    }
}
