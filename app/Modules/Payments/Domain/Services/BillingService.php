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

            $subtotal = (float) $order->subtotal;
            $tax = (float) $order->tax_amount;
            $discount = (float) $order->discount_amount;
            $total = (float) $order->total;

            $baseSubtotal = floor(($subtotal / $parts) * 100) / 100;
            $baseTax = floor(($tax / $parts) * 100) / 100;
            $baseDiscount = floor(($discount / $parts) * 100) / 100;
            $baseTotal = floor(($total / $parts) * 100) / 100;

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
            $orderSubtotal = (float) $order->subtotal;
            $orderTax = (float) $order->tax_amount;
            $orderDiscount = (float) $order->discount_amount;
            $orderTotal = (float) $order->total;



            // Calcular el subtotal total de items agrupados
            $totalGroupedSubtotal = 0;
            foreach ($groups as $group) {
                foreach ($group['item_ids'] ?? [] as $itemId) {
                    if ($item = $items->get($itemId)) {
                        $totalGroupedSubtotal += (float) $item->subtotal;
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
                        $groupSubtotal += (float) $item->subtotal;
                        $itemIds[] = $itemId;
                    }
                }

                // Calcular proporción del IVA y descuento
                $ratio = $totalGroupedSubtotal > 0 
                    ? $groupSubtotal / $totalGroupedSubtotal 
                    : (count($groups) > 0 ? 1 / count($groups) : 0);
                
                $groupTax = round($orderTax * $ratio, 2);
                $groupDiscount = round($orderDiscount * $ratio, 2);
                $groupTotal = round($groupSubtotal + $groupTax - $groupDiscount, 2);

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
                $difference = round($orderTotal - $calculatedTotal, 2);
                
                if (abs($difference) > 0.001) {
                    $lastBill = $bills[count($bills) - 1];
                    $lastBill->total = round((float) $lastBill->total + $difference, 2);
                    $lastBill->remaining_amount = $lastBill->total;
                    $lastBill->save();
                }
            }

            return $bills;
        });
    }

    /**
     * Modalidad 3: Montos personalizados por persona.
     * TOLERANTE: Ajusta la última bill si la suma difiere en centavos.
     */
    public function splitByAmounts(Order $order, array $amounts): array
    {
        if (empty($amounts)) {
            throw PaymentException::invalidSplitAmount();
        }

        // Normalizar montos a float
        $amounts = array_map('floatval', $amounts);
        $sumAmounts = round(array_sum($amounts), 2);
        $orderTotal = round((float) $order->total, 2);

        // Tolerancia de $1 por redondeo
        $difference = round($sumAmounts - $orderTotal, 2);
        if (abs($difference) > 1) {
            throw PaymentException::invalidSplitAmount();
        }

        return DB::transaction(function () use ($order, $amounts, $orderTotal, $sumAmounts) {
            $this->cancelExistingBills($order);

            $bills = [];
            $assignedTotal = 0;

            foreach ($amounts as $index => $amount) {
                $amount = round((float) $amount, 2);
                
                // La última bill absorbe cualquier diferencia por redondeo
                $isLast = ($index === count($amounts) - 1);
                if ($isLast && count($amounts) > 1) {
                    $amount = round($orderTotal - $assignedTotal, 2);
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
     */
    public function createSingleBill(Order $order): Bill
    {
        return DB::transaction(function () use ($order) {
            // Si ya existen bills no canceladas, retornar la primera
            $existing = Bill::where('order_id', $order->id)
                ->whereNotIn('status', [BillStatus::CANCELLED])
                ->first();
            
            if ($existing) {
                return $existing;
            }

            return Bill::create([
                'company_id' => $order->company_id,
                'branch_id' => $order->branch_id,
                'order_id' => $order->id,
                'bill_number' => Bill::generateBillNumber($order->order_number, 1),
                'type' => BillType::SINGLE,
                'subtotal' => (float) $order->subtotal,
                'tax_amount' => (float) $order->tax_amount,
                'discount_amount' => (float) $order->discount_amount,
                'tip_amount' => 0,
                'total' => (float) $order->total,
                'paid_amount' => 0,
                'remaining_amount' => (float) $order->total,
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
