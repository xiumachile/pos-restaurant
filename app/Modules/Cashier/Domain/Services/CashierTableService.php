<?php

namespace Modules\Cashier\Domain\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Cashier\Interfaces\Resources\TableWithBillsResource;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Events\OrderPaid;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Payments\Domain\Entities\Bill;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\Exceptions\PaymentException;
use Modules\Payments\Domain\Services\BillingService;
use Modules\Payments\Domain\Services\PaymentService;
use Modules\Payments\Domain\ValueObjects\BillStatus;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;
use Modules\Tables\Domain\Entities\RestaurantTable;

/**
 * Servicio de dominio para operaciones de caja sobre mesas.
 * 
 * Extraído de CashierTablesController en S2 para cumplir DDD:
 * - Controllers solo hacen orquestación HTTP
 * - Services contienen la lógica de negocio
 * - Queries y transformaciones están aquí
 */
class CashierTableService
{
    public function __construct(
        private PaymentService $paymentService,
        private BillingService $billingService
    ) {}

    /**
     * Estados de order que se consideran cobrables.
     */
    private function getChargeableStatuses(): array
    {
        return [
            OrderStatus::CONFIRMED,
            OrderStatus::PREPARING,
            OrderStatus::READY,
            OrderStatus::SERVED,
        ];
    }

    /**
     * Obtiene las mesas con pedidos cobrables y su información completa.
     */
    public function getTablesWithBills(int $branchId): Collection
    {
        $tableIds = Order::where('branch_id', $branchId)
            ->whereIn('status', $this->getChargeableStatuses())
            ->whereNotNull('table_id')
            ->distinct()
            ->pluck('table_id');

        $tables = RestaurantTable::whereIn('id', $tableIds)
            ->orderBy('area_code')
            ->orderBy('table_number')
            ->get();

        return $tables->map(fn($table) => $this->buildTableWithBillsData($table, $branchId));
    }

    /**
     * Construye los datos completos de una mesa con sus pedidos cobrables.
     */
    private function buildTableWithBillsData(RestaurantTable $table, int $branchId): array
    {
        $chargeableOrders = Order::where('table_id', $table->id)
            ->where('branch_id', $branchId)
            ->whereIn('status', $this->getChargeableStatuses())
            ->with(['items', 'waiter', 'bills'])
            ->orderBy('created_at', 'asc')
            ->get();

        $totalAmount = $chargeableOrders->sum('total');
        $totalItems = $chargeableOrders->sum(fn($o) => $o->items->sum('quantity'));
        $totalTax = $chargeableOrders->sum('tax_amount');
        $totalSubtotal = $chargeableOrders->sum('subtotal');

        $unservedOrders = $chargeableOrders->filter(fn($o) => $o->status !== OrderStatus::SERVED);
        $unservedItemsCount = $unservedOrders->sum(fn($o) => $o->items->sum('quantity'));

        return [
            'table_uuid' => $table->uuid,
            'table_number' => $table->table_number,
            'area_code' => $table->area_code,
            'capacity' => $table->capacity,
            'orders_count' => $chargeableOrders->count(),
            'total_items' => $totalItems,
            'subtotal' => (float) $totalSubtotal,
            'tax_amount' => (float) $totalTax,
            'total_amount' => (float) $totalAmount,
            'first_order_at' => $chargeableOrders->first()?->created_at?->toIso8601String(),
            'last_order_at' => $chargeableOrders->last()?->created_at?->toIso8601String(),
            'has_unserved_orders' => $unservedOrders->isNotEmpty(),
            'unserved_orders_count' => $unservedOrders->count(),
            'unserved_items_count' => (int) $unservedItemsCount,
            'orders' => $chargeableOrders->map(fn($order) => $this->buildOrderData($order))->values(),
        ];
    }

    private function buildOrderData(Order $order): array
    {
        return [
            'uuid' => $order->uuid,
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'subtotal' => (float) $order->subtotal,
            'tax_amount' => (float) $order->tax_amount,
            'total' => (float) $order->total,
            'waiter_name' => $order->waiter?->name,
            'served_at' => $order->served_at?->toIso8601String(),
            'items' => $order->items->map(fn($item) => [
                'id' => $item->id,
                'uuid' => $item->uuid,
                'name' => $item->name_snapshot,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price_snapshot,
                'subtotal' => (float) $item->subtotal,
                'notes' => $item->notes,
            ])->values(),
            'bills' => $order->bills->map(fn($bill) => [
                'uuid' => $bill->uuid,
                'bill_number' => $bill->bill_number,
                'type' => $bill->type->value,
                'subtotal' => (float) $bill->subtotal,
                'tax_amount' => (float) $bill->tax_amount,
                'total' => (float) $bill->total,
                'paid_amount' => (float) $bill->paid_amount,
                'remaining_amount' => (float) $bill->remaining_amount,
                'status' => $bill->status->value,
                'guest_count' => $bill->guest_count,
            ])->values(),
        ];
    }

    /**
     * Prepara las bills para los pedidos cobrables de una mesa.
     * 
     * @return array{bills: array, total_amount: float, orders_count: int}
     */
    public function prepareBillsForTable(RestaurantTable $table, int $branchId): array
    {
        $chargeableOrders = $this->getChargeableOrders($table, $branchId);

        if ($chargeableOrders->isEmpty()) {
            throw new \DomainException('La mesa no tiene pedidos servidos para cobrar.');
        }

        $bills = [];
        foreach ($chargeableOrders as $order) {
            $bills[] = $this->billingService->createSingleBill($order);
        }

        return [
            'bills' => $bills,
            'total_amount' => (float) $chargeableOrders->sum('total'),
            'orders_count' => $chargeableOrders->count(),
        ];
    }

    /**
     * Cobra todos los pedidos de una mesa con un método de pago.
     * 
     * @throws PaymentException
     * @return array Resumen del cobro
     */
    public function chargeTable(
        RestaurantTable $table,
        PaymentMethod $paymentMethod,
        array $data,
        User $user,
        int $branchId
    ): array {
        $chargeableOrders = $this->getChargeableOrders($table, $branchId);

        if ($chargeableOrders->isEmpty()) {
            throw new \DomainException('La mesa no tiene pedidos servidos para cobrar.');
        }

        $cashSession = $this->getOpenCashSession($branchId);
        $totalAmount = $chargeableOrders->sum('total');
        $totalTip = (float) ($data['tip_amount'] ?? 0);
        $baseIdempotencyKey = $data['idempotency_key'];

        $payments = [];

        foreach ($chargeableOrders as $index => $order) {
            $orderTip = $totalAmount > 0
                ? round($totalTip * ($order->total / $totalAmount), 2)
                : 0;

            $orderIdempotencyKey = $index === 0
                ? $baseIdempotencyKey
                : $this->deriveIdempotencyKey($baseIdempotencyKey, $index);

            $payment = $this->paymentService->registerPayment(
                order: $order,
                paymentMethod: $paymentMethod,
                amount: (float) $order->total,
                idempotencyKey: $orderIdempotencyKey,
                bill: null,
                cashSession: $cashSession,
                userId: $user->id,
                tipAmount: $orderTip,
                referenceCode: $data['reference_code'] ?? null,
                notes: $data['notes'] ?? null
            );

            $payments[] = $payment;

            $order->cashier_id = $user->id;
            $order->paid_at = now();
            $order->status = OrderStatus::PAID;
            $order->save();

            event(new OrderPaid($order));
        }

        $this->releaseTableIfEmpty($table, $branchId, $user);

        return [
            'success' => true,
            'orders_charged' => count($payments),
            'total_charged' => (float) $totalAmount,
            'total_tip' => $totalTip,
            'grand_total' => (float) ($totalAmount + $totalTip),
            'table_freed' => true,
            'table_number' => $table->table_number,
        ];
    }

    /**
     * Cobra una bill específica (pago dividido).
     * 
     * @throws PaymentException
     * @return array Resumen del pago
     */
    public function payBill(
        Bill $bill,
        PaymentMethod $paymentMethod,
        array $data,
        User $user,
        int $branchId
    ): array {
        if ($bill->status === BillStatus::PAID) {
            throw new \DomainException('Esta sub-cuenta ya fue pagada.');
        }

        if ($bill->remaining_amount <= 0) {
            throw new \DomainException('Esta sub-cuenta ya está completamente pagada.');
        }

        // Determinar monto a pagar
        $requestedAmount = isset($data['amount']) ? (float) $data['amount'] : null;
        
        if ($requestedAmount !== null) {
            if ($requestedAmount > (float) $bill->remaining_amount + 0.01) {
                throw new \DomainException(
                    "El monto solicitado (\${$requestedAmount}) excede el pendiente (\${$bill->remaining_amount})."
                );
            }
            $amountToPay = $requestedAmount;
        } else {
            $amountToPay = (float) $bill->remaining_amount;
        }

        $cashSession = $this->getOpenCashSession($branchId);
        $tipAmount = (float) ($data['tip_amount'] ?? 0);

        $payment = $this->paymentService->registerPayment(
            order: $bill->order,
            paymentMethod: $paymentMethod,
            amount: $amountToPay,
            idempotencyKey: $data['idempotency_key'],
            bill: $bill,
            cashSession: $cashSession,
            userId: $user->id,
            tipAmount: $tipAmount,
            referenceCode: $data['reference_code'] ?? null,
            notes: $data['notes'] ?? null
        );

        // Actualizar bill
        $bill->paid_amount = (float) $bill->paid_amount + $amountToPay;
        $bill->remaining_amount = (float) $bill->total - (float) $bill->paid_amount;
        if ($bill->remaining_amount <= 0.01) {
            $bill->status = BillStatus::PAID;
        }
        $bill->save();

        // Verificar si todas las bills del order están pagadas
        $order = $bill->order;
        $allBillsPaid = Bill::where('order_id', $order->id)
            ->whereNotIn('status', [BillStatus::CANCELLED])
            ->where('status', '!=', BillStatus::PAID)
            ->count() === 0;

        $orderTransitionedToPaid = false;
        if ($allBillsPaid && $order->status->isChargeable()) {
            $order->cashier_id = $user->id;
            $order->paid_at = now();
            $order->status = OrderStatus::PAID;
            $order->save();
            event(new OrderPaid($order));
            $orderTransitionedToPaid = true;

            $table = $order->table;
            if ($table) {
                $this->releaseTableIfEmpty($table, $branchId, $user);
            }
        }

        return [
            'success' => true,
            'bill_uuid' => $bill->uuid,
            'bill_paid' => $bill->status === BillStatus::PAID,
            'paid_amount' => (float) $bill->paid_amount,
            'remaining_amount' => (float) $bill->remaining_amount,
            'order_transitioned_to_paid' => $orderTransitionedToPaid,
            'amount_paid' => $amountToPay,
            'tip_amount' => $tipAmount,
        ];
    }

    /**
     * Libera la mesa si no tiene pedidos activos.
     */
    private function releaseTableIfEmpty(RestaurantTable $table, int $branchId, User $user): void
    {
        // Validación defensiva (THREAT-007 de S1)
        if ($table->company_id !== $user->company_id || $table->branch_id !== $user->branch_id) {
            throw new \DomainException('No autorizado para actualizar esta mesa');
        }

        $activeOrdersCount = Order::where('table_id', $table->id)
            ->where('branch_id', $branchId)
            ->whereIn('status', [
                OrderStatus::DRAFT,
                OrderStatus::CONFIRMED,
                OrderStatus::PREPARING,
                OrderStatus::READY,
                OrderStatus::SERVED,
            ])
            ->count();

        if ($activeOrdersCount === 0) {
            DB::table('restaurant_tables')
                ->where('id', $table->id)
                ->update([
                    'status' => 'available',
                    'updated_at' => now(),
                ]);
        }
    }

    private function getChargeableOrders(RestaurantTable $table, int $branchId): Collection
    {
        return Order::where('table_id', $table->id)
            ->where('branch_id', $branchId)
            ->whereIn('status', $this->getChargeableStatuses())
            ->orderBy('created_at', 'asc')
            ->get();
    }

    private function getOpenCashSession(int $branchId): ?CashSession
    {
        return CashSession::where('branch_id', $branchId)
            ->where('status', CashSessionStatus::OPEN)
            ->first();
    }

    /**
     * Deriva una clave de idempotencia única para cada order de la mesa.
     */
    private function deriveIdempotencyKey(string $baseKey, int $index): string
    {
        $hash = md5($baseKey . ':' . $index);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12)
        );
    }
}
