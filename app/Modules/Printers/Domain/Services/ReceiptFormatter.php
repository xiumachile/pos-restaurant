<?php

namespace Modules\Printers\Domain\Services;

use App\Shared\Domain\Services\MoneyFormatter;
use Modules\Companies\Domain\Entities\Company;

/**
 * Formateador de tickets de cliente.
 * Genera recibos completos con logo, detalle, totales y código de barras.
 *
 * No hardcodea símbolos de moneda ni tasas de impuesto:
 * - Moneda: vía MoneyFormatter + Company.settings.currency
 * - Impuesto: vía $data['tax_label'] construido desde snapshots históricos
 */
class ReceiptFormatter
{
    public function __construct(
        private EscPosService $escPos = new EscPosService(),
        private MoneyFormatter $moneyFormatter = new MoneyFormatter()
    ) {}

    /**
     * Formatea un número como moneda según la configuración de la empresa.
     *
     * Si no se provee Company, usa defaults de Chile (CLP).
     *
     * @deprecated Usar MoneyFormatter directamente cuando se requiera control preciso
     */
    private function formatCurrency(float $amount, ?Company $company = null): string
    {
        if ($company) {
            return $this->moneyFormatter->format($amount, $company);
        }

        // Fallback: defaults de Chile (CLP)
        return number_format($amount, 0, ',', '.');
    }

    /**
     * Genera un ticket de cliente completo.
     *
     * @param array $data Datos del ticket:
     *   - company: instancia de Company (opcional, para formateo de moneda)
     *   - company_name: nombre de la empresa
     *   - branch_name: nombre de la sucursal
     *   - order_number: número de pedido
     *   - date: fecha del pedido
     *   - waiter: nombre del garzón
     *   - items: array de items [{name, qty, price, subtotal}]
     *   - subtotal: subtotal
     *   - tax_label: etiqueta dinámica del impuesto (ej: "IVA (19%)", "IVA (21%)")
     *   - tax: monto del impuesto (solo se imprime si > 0)
     *   - discount: descuento
     *   - total: total
     *   - payment_method: método de pago
     *   - barcode: código de barras (opcional)
     */
    public function format(array $data): string
    {
        $output = $this->escPos->initialize();

        $company = $data['company'] ?? null;
        $formatAmount = fn(float $amount) => $this->formatCurrency($amount, $company);

        // Encabezado con nombre de empresa
        $output .= $this->escPos->alignCenter($this->escPos->doubleSize($data['company_name'] ?? 'Restaurant'));
        $output .= $this->escPos->alignCenter($data['branch_name'] ?? 'Sucursal');
        $output .= $this->escPos->lineBreak();

        // Información del pedido
        $output .= $this->escPos->alignLeft("Pedido: #" . ($data['order_number'] ?? ''));
        $output .= $this->escPos->alignLeft("Fecha: " . ($data['date'] ?? now()->format('d/m/Y H:i')));
        $output .= $this->escPos->alignLeft("Atendió: " . ($data['waiter'] ?? 'N/A'));

        $output .= $this->escPos->separator();

        // Detalle de items
        $output .= $this->escPos->alignLeft($this->escPos->bold("DETALLE"));
        $output .= $this->escPos->lineBreak();

        foreach (($data['items'] ?? []) as $item) {
            $qty = $item['qty'] ?? 1;
            $name = $item['name'] ?? 'Producto';
            $price = $item['price'] ?? 0;
            $subtotal = $item['subtotal'] ?? ($qty * $price);

            // Nombre del producto
            $output .= $this->escPos->alignLeft("{$qty}x {$name}");

            // Precio unitario y subtotal (alineados)
            $priceFormatted = $formatAmount((float) $price);
            $subtotalFormatted = $formatAmount((float) $subtotal);
            $output .= $this->escPos->alignRight("   {$priceFormatted}    {$subtotalFormatted}");
        }

        $output .= $this->escPos->separator();

        // Totales
        $subtotal = (float) ($data['subtotal'] ?? 0);
        $tax = (float) ($data['tax'] ?? 0);
        $discount = (float) ($data['discount'] ?? 0);
        $total = (float) ($data['total'] ?? 0);

        $output .= $this->escPos->alignRight("Subtotal: " . $formatAmount($subtotal));

        if ($discount > 0) {
            $output .= $this->escPos->alignRight("Descuento: -" . $formatAmount($discount));
        }

        // Impuesto: solo imprimir si hay monto, con etiqueta dinámica
        if ($tax > 0) {
            $taxLabel = $data['tax_label'] ?? 'Impuesto';
            $output .= $this->escPos->alignRight("{$taxLabel}: " . $formatAmount($tax));
        }

        $output .= $this->escPos->separator();
        $output .= $this->escPos->alignRight($this->escPos->doubleSize("TOTAL: " . $formatAmount($total)));
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
