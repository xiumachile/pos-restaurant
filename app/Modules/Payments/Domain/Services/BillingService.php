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

            Log::info('splitEqual creado', [
                'order' => $order->order_number,
                'parts' => $parts,
                'total_per_part' => $bills[0]->total,
            ]);

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

            Log::debug('splitByItems inicio', [
                'order' => $order->order_number,
                'order_total' => $orderTotal,
                'order_subtotal' => $orderSubtotal,
                'order_tax' => $orderTax,
                'groups_count' => count($groups),
                'items_count' => $items->count(),
            ]);

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

                $ratio = $groupSubtotal / $totalGroupedSubtotal;
                $groupTax = round($orderTax * $ratio, 2);
                $groupDiscount = round($orderDiscount * $ratio, 2);
                $groupTotal = round($groupSubtotal + $groupTax - $groupDiscount, 2);

                $calculatedTotal += $groupTotal;

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

            // Ajuste por redondeo en la última bill
            $difference = round($orderTotal - $calculatedTotal, 2);
            if (abs($difference) > 0.001 && count($bills) > 0) {
                $lastBill = $bills[count($bills) - 1];
                $lastBill->total = round((float) $lastBill->total + $difference, 2);
                $lastBill->remaining_amount = $lastBill->total;
                $lastBill->save();
                
                Log::info('splitByItems ajuste redondeo', [
                    'order' => $order->order_number,
                    'difference' => $difference,
                    'adjusted_bill' => $lastBill->bill_number,
                ]);
            }

            Log::info('splitByItems creado', [
                'order' => $order->order_number,
                'bills_count' => count($bills),
                'total' => $orderTotal,
            ]);

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

        Log::debug('splitByAmounts inicio', [
            'order' => $order->order_number,
            'amounts' => $amounts,
            'sum' => $sumAmounts,
            'order_total' => $orderTotal,
            'difference' => round($sumAmounts - $orderTotal, 2),
        ]);

        // Tolerancia de $1 por redondeo
        $difference = round($sumAmounts - $orderTotal, 2);
        if (abs($difference) > 1) {
            Log::warning('splitByAmounts diferencia grande', [
                'order' => $order->order_number,
                'sum' => $sumAmounts,
                'order_total' => $orderTotal,
                'difference' => $difference,
            ]);
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

            Log::info('splitByAmounts creado', [
                'order' => $order->order_number,
                'bills_count' => count($bills),
                'total_assigned' => $assignedTotal,
                'order_total' => $orderTotal,
            ]);

            return $bills;
        });
    }

    /**
     * Cancela bills existentes de un order.
     */
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
