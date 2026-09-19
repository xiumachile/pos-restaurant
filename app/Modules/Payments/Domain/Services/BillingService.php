<?php

namespace Modules\Payments\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Orders\Domain\Entities\Order;
use Modules\Payments\Domain\Entities\Bill;
use Modules\Payments\Domain\Exceptions\PaymentException;
use Modules\Payments\Domain\ValueObjects\BillStatus;
use Modules\Payments\Domain\ValueObjects\BillType;

class BillingService
{
    /**
     * Modalidad 1: N partes iguales.
     */
    public function splitEqual(Order $order, int $parts): array
    {
        if ($parts < 2) {
            throw PaymentException::invalidSplitAmount();
        }

        return DB::transaction(function () use ($order, $parts) {
            $this->cancelExistingBills($order);

            $subtotal = (int) $order->subtotal;
            $tax = (int) $order->tax_amount;
            $discount = (int) $order->discount_amount;
            $total = $order->total;

            $baseSubtotal = (int) floor($subtotal / $parts);
            $baseTax = (int) floor($tax / $parts);
            $baseDiscount = (int) floor($discount / $parts);
            $baseTotal = (int) floor($total / $parts);

            $residualSubtotal = (int) ($subtotal - ($baseSubtotal * $parts));
            $residualTax = (int) ($tax - ($baseTax * $parts));
            $residualDiscount = (int) ($discount - ($baseDiscount * $parts));
            $residualTotal = (int) ($total - ($baseTotal * $parts));

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
     * Modalidad 2: Cada comensal paga lo que consumió.
     */
    public function splitByItems(Order $order, array $groups): array
    {
        if (empty($groups)) {
            throw PaymentException::invalidSplitAmount();
        }

        return DB::transaction(function () use ($order, $groups) {
            $this->cancelExistingBills($order);

            $items = $order->items()->get()->keyBy('id');
            $orderSubtotal = (int) $order->subtotal;
            $orderTax = (int) $order->tax_amount;
            $orderDiscount = (int) $order->discount_amount;
            $orderTotal = $order->total;



            // Calcular el subtotal total de items agrupados
            $totalGroupedSubtotal = 0; // integer
            foreach ($groups as $group) {
                foreach ($group['item_ids'] ?? [] as $itemId) {
                    if ($item = $items->get($itemId)) {
                        $totalGroupedSubtotal += (int) $item->subtotal;
                    }
                }
            }

            // Si no hay items agrupados, usar el subtotal del order completo
            if ($totalGroupedSubtotal <= 0) {
                $totalGroupedSubtotal = $orderSubtotal > 0 ? $orderSubtotal : 1;
            }

            $bills = [];
            $calculatedTotal = 0;

            foreach ($groups as $index => $group) {
                $groupSubtotal = 0;
                $itemIds = [];
                foreach ($group['item_ids'] ?? [] as $itemId) {
                    if ($item = $items->get($itemId)) {
                        $groupSubtotal += (int) $item->subtotal;
                        $itemIds[] = $itemId;
                    }
                }

                // Calcular proporción del IVA y descuento
                $ratio = $totalGroupedSubtotal > 0 
                    ? $groupSubtotal / $totalGroupedSubtotal 
                    : (count($groups) > 0 ? 1 / count($groups) : 0);
                
                $groupTax = (int) round($orderTax * $ratio);
                $groupDiscount = (int) round($orderDiscount * $ratio);
                $groupTotal = (int) round($groupSubtotal + $groupTax - $groupDiscount);

                Log::debug('splitByItems grupo', [
                    'group_index' => $index,
                    'groupSubtotal' => $groupSubtotal,
                    'ratio' => $ratio,
                    'groupTax' => $groupTax,
                    'groupDiscount' => $groupDiscount,
                    'groupTotal' => $groupTotal,
                ]);

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

                $calculatedTotal += $groupTotal;
            }

            // Ajuste de redondeo: la última bill absorbe diferencia de centavos
            if (count($bills) > 0) {
                $difference = (int) round($orderTotal - $calculatedTotal);
                
                if ($difference !== 0  // ADR-018: comparación exacta) {
                    $lastBill = $bills[count($bills) - 1];
                    $lastBill->total = (int) round((int) $lastBill->total + $difference);
                    $lastBill->remaining_amount = $lastBill->total;
                    $lastBill->save();
                }
            }

            return $bills;
        });
    }

    /**
     * Modalidad 3: Montos personalizados por persona.
     * TOLERANTE: Ajusta la última bill si la suma difiere en ±1 CLP (redondeo entero).
     */
    public function splitByAmounts(Order $order, array $amounts): array
    {
        if (empty($amounts)) {
            throw PaymentException::invalidSplitAmount();
        }

        // ADR-018: Normalizar montos a entero
        $amounts = array_map(static fn ($amount): int => (int) $amount, $amounts);  // ADR-018: entero end-to-end
        $sumAmounts = (int) round(array_sum($amounts));
        $orderTotal = (int) round($order->total);

        // Tolerancia de $1 por redondeo
        $difference = (int) round($sumAmounts - $orderTotal);
        if (abs($difference) > 1) {
            throw PaymentException::invalidSplitAmount();
        }

        return DB::transaction(function () use ($order, $amounts, $orderTotal, $sumAmounts) {
            $this->cancelExistingBills($order);

            $bills = [];
            $assignedTotal = 0;

            foreach ($amounts as $index => $amount) {
                $amount = (int) $amount;  // ADR-018: sin aritmética flotante
                
                // La última bill absorbe cualquier diferencia por redondeo
                $isLast = ($index === count($amounts) - 1);
                if ($isLast && count($amounts) > 1) {
                    $amount = (int) round($orderTotal - $assignedTotal);
                }
                
                $assignedTotal += $amount;

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
     * Cancela bills existentes de un order.
     */

    /**
     * Crea una bill única para un order (sin dividir).
     * Usado cuando se quiere cobrar el order completo con pagos divididos por método.
     * 
     * Si ya existe una bill:
     * - Con total correcto → la retorna tal cual
     * - Con total = 0 (corrupta) → la elimina y crea una nueva
     */
    public function createSingleBill(Order $order): Bill
    {
        return DB::transaction(function () use ($order) {
            // Buscar bills existentes no canceladas
            $existing = Bill::where('order_id', $order->id)
                ->whereNotIn('status', [BillStatus::CANCELLED])
                ->first();
            
            if ($existing) {
                // Si la bill tiene total correcto, retornarla
                if ((int) $existing->total > 0 && (int) $existing->total === (int) $order->total) {  // ADR-018: comparación exacta, sin epsilon
                    return $existing;
                }
                
                // Si la bill está corrupta (total=0), eliminarla para recrearla
                if ((int) $existing->total == 0 && (int) $existing->paid_amount == 0) {
                    $existing->delete();
                } else {
                    // Bill tiene datos pero diferente total - retornar como está
                    return $existing;
                }
            }

            // Crear nueva bill con los totales del order
            $orderTotal = $order->total;
            $orderSubtotal = (int) $order->subtotal;
            $orderTax = (int) $order->tax_amount;
            $orderDiscount = (int) $order->discount_amount;

            return Bill::create([
                'company_id' => $order->company_id,
                'branch_id' => $order->branch_id,
                'order_id' => $order->id,
                'bill_number' => Bill::generateBillNumber($order->order_number, 1),
                'type' => BillType::SINGLE,
                'subtotal' => $orderSubtotal,
                'tax_amount' => $orderTax,
                'discount_amount' => $orderDiscount,
                'tip_amount' => 0,
                'total' => $orderTotal,
                'paid_amount' => 0,
                'remaining_amount' => $orderTotal,
                'status' => BillStatus::OPEN,
                'guest_count' => 1,
            ]);
        });
    }

    private function cancelExistingBills(Order $order): void
    {
        Bill::where('order_id', $order->id)
            ->where('status', BillStatus::OPEN)
            ->update([
                'status' => BillStatus::CANCELLED,
                'updated_at' => now(),
            ]);
    }
}
