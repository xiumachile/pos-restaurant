<?php

namespace Modules\Payments\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Orders\Domain\Entities\Order;
use Modules\Payments\Domain\Entities\Bill;
use Modules\Payments\Domain\Exceptions\PaymentException;
use Modules\Payments\Domain\ValueObjects\BillStatus;
use Modules\Payments\Domain\ValueObjects\BillType;

/**
 * Servicio de dominio para facturación y Split Bill.
 * Implementa las 3 modalidades de división de cuenta según Arquitectura v1.1 Sección 11.3.
 */
class BillingService
{
    /**
     * Genera sub-cuentas por partes iguales.
     * Modalidad 1: N personas dividen el total exacto.
     */
    public function splitEqual(Order $order, int $parts): array
    {
        if ($parts < 2) {
            throw PaymentException::invalidSplitAmount();
        }

        return DB::transaction(function () use ($order, $parts) {
            // Cancelar bills anteriores si existen
            $this->cancelExistingBills($order);

            $subtotal = (float) $order->subtotal;
            $tax = (float) $order->tax_amount;
            $discount = (float) $order->discount_amount;
            $total = (float) $order->total;

            // División base con redondeo hacia abajo
            $baseSubtotal = floor(($subtotal / $parts) * 100) / 100;
            $baseTax = floor(($tax / $parts) * 100) / 100;
            $baseDiscount = floor(($discount / $parts) * 100) / 100;
            $baseTotal = floor(($total / $parts) * 100) / 100;

            // Calcular residuos para asignar a la primera bill
            $residualSubtotal = round($subtotal - ($baseSubtotal * $parts), 2);
            $residualTax = round($tax - ($baseTax * $parts), 2);
            $residualDiscount = round($discount - ($baseDiscount * $parts), 2);
            $residualTotal = round($total - ($baseTotal * $parts), 2);

            $bills = [];
            for ($i = 1; $i <= $parts; $i++) {
                $isFirst = ($i === 1);
                $billSubtotal = $baseSubtotal + ($isFirst ? $residualSubtotal : 0);
                $billTax = $baseTax + ($isFirst ? $residualTax : 0);
                $billDiscount = $baseDiscount + ($isFirst ? $residualDiscount : 0);
                $billTotal = $baseTotal + ($isFirst ? $residualTotal : 0);

                $bills[] = Bill::create([
                    'company_id' => $order->company_id,
                    'branch_id' => $order->branch_id,
                    'order_id' => $order->id,
                    'bill_number' => Bill::generateBillNumber($order->order_number, $i),
                    'type' => BillType::EQUAL_SPLIT,
                    'subtotal' => $billSubtotal,
                    'tax_amount' => $billTax,
                    'discount_amount' => $billDiscount,
                    'tip_amount' => 0,
                    'total' => $billTotal,
                    'paid_amount' => 0,
                    'remaining_amount' => $billTotal,
                    'status' => BillStatus::OPEN,
                    'guest_count' => 1,
                ]);
            }

            return $bills;
        });
    }

    /**
     * Genera sub-cuentas por ítems seleccionados.
     * Modalidad 2: Cada comensal paga lo que consumió.
     *
     * @param Order $order
     * @param array $groups Array de grupos: [['item_ids' => [1,2], 'guest_count' => 2], ...]
     */
    public function splitByItems(Order $order, array $groups): array
    {
        if (empty($groups)) {
            throw PaymentException::invalidSplitAmount();
        }

        return DB::transaction(function () use ($order, $groups) {
            $this->cancelExistingBills($order);

            $items = $order->items()->get()->keyBy('id');
            $orderSubtotal = (float) $order->subtotal;
            $orderTax = (float) $order->tax_amount;
            $orderDiscount = (float) $order->discount_amount;

            // Calcular el total de items agrupados para prorratear impuestos
            $totalGroupedSubtotal = 0;
            foreach ($groups as $group) {
                foreach ($group['item_ids'] ?? [] as $itemId) {
                    if ($item = $items->get($itemId)) {
                        $totalGroupedSubtotal += (float) $item->subtotal;
                    }
                }
            }

            if ($totalGroupedSubtotal <= 0) {
                throw PaymentException::invalidSplitAmount();
            }

            $bills = [];
            foreach ($groups as $index => $group) {
                $groupSubtotal = 0;
                $itemIds = [];
                foreach ($group['item_ids'] ?? [] as $itemId) {
                    if ($item = $items->get($itemId)) {
                        $groupSubtotal += (float) $item->subtotal;
                        $itemIds[] = $itemId;
                    }
                }

                // Prorratear impuestos y descuentos según proporción del subtotal
                $ratio = $groupSubtotal / $totalGroupedSubtotal;
                $groupTax = round($orderTax * $ratio, 2);
                $groupDiscount = round($orderDiscount * $ratio, 2);
                $groupTotal = round($groupSubtotal + $groupTax - $groupDiscount, 2);

                $bills[] = Bill::create([
                    'company_id' => $order->company_id,
                    'branch_id' => $order->branch_id,
                    'order_id' => $order->id,
                    'bill_number' => Bill::generateBillNumber($order->order_number, $index + 1),
                    'type' => BillType::BY_ITEMS,
                    'subtotal' => $groupSubtotal,
                    'tax_amount' => $groupTax,
                    'discount_amount' => $groupDiscount,
                    'tip_amount' => 0,
                    'total' => $groupTotal,
                    'paid_amount' => 0,
                    'remaining_amount' => $groupTotal,
                    'status' => BillStatus::OPEN,
                    'guest_count' => $group['guest_count'] ?? 1,
                    'item_ids' => $itemIds,
                ]);
            }

            return $bills;
        });
    }

    /**
     * Genera sub-cuentas por montos personalizados.
     * Modalidad 3: Abonos parciales hasta completar la suma.
     *
     * @param Order $order
     * @param array $amounts Array de montos: [25000, 15000, 10000]
     */
    public function splitByAmounts(Order $order, array $amounts): array
    {
        if (empty($amounts)) {
            throw PaymentException::invalidSplitAmount();
        }

        $sumAmounts = array_sum($amounts);
        $orderTotal = (float) $order->total;

        // Validar que la suma de montos no exceda el total (con tolerancia de 0.01)
        if ($sumAmounts > $orderTotal + 0.01) {
            throw PaymentException::invalidSplitAmount();
        }

        return DB::transaction(function () use ($order, $amounts) {
            $this->cancelExistingBills($order);

            $bills = [];
            foreach ($amounts as $index => $amount) {
                $amount = (float) $amount;
                $bills[] = Bill::create([
                    'company_id' => $order->company_id,
                    'branch_id' => $order->branch_id,
                    'order_id' => $order->id,
                    'bill_number' => Bill::generateBillNumber($order->order_number, $index + 1),
                    'type' => BillType::CUSTOM_AMOUNT,
                    'subtotal' => $amount,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'tip_amount' => 0,
                    'total' => $amount,
                    'paid_amount' => 0,
                    'remaining_amount' => $amount,
                    'status' => BillStatus::OPEN,
                    'guest_count' => 1,
                ]);
            }

            return $bills;
        });
    }

    /**
     * Calcula la propina según el porcentaje.
     * Según Arquitectura v1.1 Sección 11.4: configurable pre/post impuesto.
     */
    public function calculateTip(float $baseAmount, float $tipPercentage, bool $postTax = false, float $taxAmount = 0): float
    {
        $tipBase = $postTax ? ($baseAmount + $taxAmount) : $baseAmount;
        return round($tipBase * ($tipPercentage / 100), 2);
    }

    /**
     * Cancela bills existentes de un pedido (para regenerar split).
     */
    private function cancelExistingBills(Order $order): void
    {
        $existingBills = Bill::where('order_id', $order->id)
            ->whereIn('status', [BillStatus::OPEN, BillStatus::PARTIAL])
            ->get();

        foreach ($existingBills as $bill) {
            // Si ya tiene pagos parciales, no cancelar (validación de negocio)
            if ((float) $bill->paid_amount > 0) {
                throw PaymentException::invalidSplitAmount();
            }
            $bill->status = BillStatus::CANCELLED;
            $bill->save();
        }
    }
}
