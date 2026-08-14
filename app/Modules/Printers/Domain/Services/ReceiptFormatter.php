<?php

namespace Modules\Printers\Domain\Services;

/**
 * Formateador de tickets de cliente.
 * Genera recibos completos con logo, detalle, totales y código de barras.
 */
class ReceiptFormatter
{
    public function __construct(
        private EscPosService $escPos = new EscPosService()
    ) {}

    /**
     * Formatea un número como moneda CLP.
     */
    private function formatCurrency(float $amount): string
    {
        return '$' . number_format($amount, 0, ',', '.');
    }

    /**
     * Genera un ticket de cliente completo.
     *
     * @param array $data Datos del ticket:
     *   - company_name: nombre de la empresa
     *   - branch_name: nombre de la sucursal
     *   - order_number: número de pedido
     *   - date: fecha del pedido
     *   - waiter: nombre del garzón
     *   - items: array de items [{name, qty, price, subtotal}]
     *   - subtotal: subtotal
     *   - tax: impuesto
     *   - discount: descuento
     *   - total: total
     *   - payment_method: método de pago
     *   - barcode: código de barras (opcional)
     */
    public function format(array $data): string
    {
        $output = $this->escPos->initialize();
        
        // Encabezado con nombre de empresa
        $output .= $this->escPos->alignCenter($this->escPos->doubleSize($data['company_name']));
        $output .= $this->escPos->alignCenter($data['branch_name']);
        $output .= $this->escPos->lineBreak();
        
        // Información del pedido
        $output .= $this->escPos->alignLeft("Pedido: #" . $data['order_number']);
        $output .= $this->escPos->alignLeft("Fecha: " . ($data['date'] ?? now()->format('d/m/Y H:i')));
        $output .= $this->escPos->alignLeft("Atendió: " . ($data['waiter'] ?? 'N/A'));
        
        $output .= $this->escPos->separator();
        
        // Detalle de items
        $output .= $this->escPos->alignLeft($this->escPos->bold("DETALLE"));
        $output .= $this->escPos->lineBreak();
        
        foreach ($data['items'] as $item) {
            $qty = $item['qty'] ?? 1;
            $name = $item['name'] ?? 'Producto';
            $price = $item['price'] ?? 0;
            $subtotal = $item['subtotal'] ?? ($qty * $price);
            
            // Nombre del producto
            $output .= $this->escPos->alignLeft("{$qty}x {$name}");
            
            // Precio unitario y subtotal (alineados)
            $priceFormatted = $this->formatCurrency($price);
            $subtotalFormatted = $this->formatCurrency($subtotal);
            $output .= $this->escPos->alignRight("   {$priceFormatted}    {$subtotalFormatted}");
        }
        
        $output .= $this->escPos->separator();
        
        // Totales
        $subtotal = $data['subtotal'] ?? 0;
        $tax = $data['tax'] ?? 0;
        $discount = $data['discount'] ?? 0;
        $total = $data['total'] ?? 0;
        
        $output .= $this->escPos->alignRight("Subtotal: " . $this->formatCurrency($subtotal));
        
        if ($discount > 0) {
            $output .= $this->escPos->alignRight("Descuento: -" . $this->formatCurrency($discount));
        }
        
        $output .= $this->escPos->alignRight("IVA (19%): " . $this->formatCurrency($tax));
        $output .= $this->escPos->separator();
        $output .= $this->escPos->alignRight($this->escPos->doubleSize("TOTAL: " . $this->formatCurrency($total)));
        $output .= $this->escPos->lineBreak();
        
        // Método de pago
        if (!empty($data['payment_method'])) {
            $output .= $this->escPos->alignCenter("Pago: " . $data['payment_method']);
            $output .= $this->escPos->lineBreak();
        }
        
        // Código de barras (si existe)
        if (!empty($data['barcode'])) {
            $output .= $this->escPos->alignCenter($this->escPos->barcode($data['barcode']));
        }
        
        // Mensaje de agradecimiento
        $output .= $this->escPos->alignCenter($this->escPos->bold("¡Gracias por su visita!"));
        $output .= $this->escPos->alignCenter("Vuelva pronto");
        
        $output .= $this->escPos->cut();
        
        return $output;
    }
}
