<?php

namespace Modules\Cashier\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Events\OrderPaid;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Payments\Domain\Entities\Bill;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\Exceptions\PaymentException;
use Modules\Payments\Domain\Services\PaymentService;
use Modules\Payments\Domain\Services\BillingService;
use Modules\Payments\Domain\ValueObjects\BillStatus;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Payments\Interfaces\Resources\BillResource;

class CashierTablesController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private BillingService $billingService
    ) {}

    
    /**
     * Estados de order que se consideran cobrables.
     * Incluye todos los estados posteriores al envío a cocina.
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

public function tablesWithBills(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;

        $tableIds = Order::where('branch_id', $branchId)
            ->whereIn('status', $this->getChargeableStatuses())
            ->whereNotNull('table_id')
            ->distinct()
            ->pluck('table_id');

        $tables = RestaurantTable::whereIn('id', $tableIds)
            ->orderBy('area_code')
            ->orderBy('table_number')
            ->get();

        $response = $tables->map(function ($table) use ($branchId) {
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

            // Calcular órdenes no servidas (para advertencia)
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
                'orders' => $chargeableOrders->map(fn($order) => [
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
                ])->values(),
            ];
        });

        return response()->json(['data' => $response]);
    }

    /**
     * POST /api/v1/cashier/tables/{tableUuid}/prepare-bills
     * Crea una bill única por cada order servido de la mesa.
     * Usado antes de abrir el modal de pagos divididos.
     * Si ya existen bills, las retorna sin crear nuevas.
     */
    public function prepareBills(Request $request, string $tableUuid): JsonResponse
    {
        $user = $request->user();
        $branchId = $user->branch_id;

        $table = RestaurantTable::where('uuid', $tableUuid)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $chargeableOrders = Order::where('table_id', $table->id)
            ->where('branch_id', $branchId)
            ->whereIn('status', $this->getChargeableStatuses())
            ->orderBy('created_at', 'asc')
            ->get();

        $servedOrders = $chargeableOrders; // Mantener variable por compatibilidad

        if ($servedOrders->isEmpty()) {
            return response()->json([
                'error' => 'no_orders_to_charge',
                'message' => 'La mesa no tiene pedidos servidos para cobrar.',
            ], 422);
        }

        $bills = [];
        foreach ($servedOrders as $order) {
            $bill = $this->billingService->createSingleBill($order);
            $bills[] = $bill;
        }

        return response()->json([
            'data' => [
                'bills' => BillResource::collection($bills),
                'total_amount' => (float) $servedOrders->sum('total'),
                'orders_count' => $servedOrders->count(),
            ],
        ]);
    }

    public function chargeTable(Request $request, string $tableUuid): JsonResponse
    {
        $user = $request->user();
        $branchId = $user->branch_id;

        $validated = $request->validate([
            'payment_method_uuid' => ['required', 'uuid', 'exists:payment_methods,uuid'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'tip_amount' => ['nullable', 'numeric', 'min:0'],
            'reference_code' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        $table = RestaurantTable::where('uuid', $tableUuid)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $chargeableOrders = Order::where('table_id', $table->id)
            ->where('branch_id', $branchId)
            ->whereIn('status', $this->getChargeableStatuses())
            ->orderBy('created_at', 'asc')
            ->get();

        $servedOrders = $chargeableOrders; // Mantener variable por compatibilidad

        if ($servedOrders->isEmpty()) {
            return response()->json([
                'error' => 'no_orders_to_charge',
                'message' => 'La mesa no tiene pedidos servidos para cobrar.',
            ], 422);
        }

        $paymentMethod = PaymentMethod::forBranch($branchId)
            ->where('uuid', $validated['payment_method_uuid'])
            ->firstOrFail();

        // ✅ FIX: Buscar sesión de caja abierta para asociar los pagos
        $cashSession = CashSession::where('branch_id', $branchId)
            ->where('status', CashSessionStatus::OPEN)
            ->first();

        $totalAmount = $servedOrders->sum('total');
        $totalTip = (float) ($validated['tip_amount'] ?? 0);

        try {
            $payments = [];
            $baseIdempotencyKey = $validated['idempotency_key'];

            foreach ($servedOrders as $index => $order) {
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
                    cashSession: $cashSession, // ✅ FIX: asociar a sesión abierta
                    userId: $user->id,
                    tipAmount: $orderTip,
                    referenceCode: $validated['reference_code'] ?? null,
                    notes: $validated['notes'] ?? null
                );

                $payments[] = $payment;

                $order->cashier_id = $user->id;
                $order->paid_at = now();
                $order->status = OrderStatus::PAID;
                $order->save();

                event(new OrderPaid($order));
            }

            DB::table('restaurant_tables')
                ->where('id', $table->id)
                ->update([
                    'status' => 'available',
                    'updated_at' => now(),
                ]);

            return response()->json([
                'data' => [
                    'success' => true,
                    'orders_charged' => count($payments),
                    'total_charged' => (float) $totalAmount,
                    'total_tip' => $totalTip,
                    'grand_total' => (float) ($totalAmount + $totalTip),
                    'table_freed' => true,
                    'table_number' => $table->table_number,
                ],
            ]);

        } catch (PaymentException $e) {
            return response()->json([
                'error' => 'payment_failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

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

    /**
     * POST /api/v1/cashier/bills/{billUuid}/pay
     * Cobra una sub-cuenta (bill) específica de un order dividido.
     * Cuando todas las bills del order están paid → order pasa a paid.
     * Cuando todos los orders de la mesa están paid → mesa se libera.
     */
    public function payBill(Request $request, string $billUuid): JsonResponse
    {
        $user = $request->user();
        $branchId = $user->branch_id;

        $validated = $request->validate([
            'payment_method_uuid' => ['required', 'uuid', 'exists:payment_methods,uuid'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'tip_amount' => ['nullable', 'numeric', 'min:0'],
            'reference_code' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        $bill = Bill::where('uuid', $billUuid)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        if ($bill->status === BillStatus::PAID) {
            return response()->json([
                'error' => 'bill_already_paid',
                'message' => 'Esta sub-cuenta ya fue pagada.',
            ], 422);
        }

        if ($bill->remaining_amount <= 0) {
            return response()->json([
                'error' => 'bill_fully_paid',
                'message' => 'Esta sub-cuenta ya está completamente pagada.',
            ], 422);
        }

        // Determinar monto a pagar:
        // - Si se provee 'amount', usarlo (para pagos divididos)
        // - Si no, usar remaining_amount (pago completo, comportamiento legacy)
        $requestedAmount = isset($validated['amount']) ? (float) $validated['amount'] : null;
        
        if ($requestedAmount !== null) {
            if ($requestedAmount > (float) $bill->remaining_amount + 0.01) {
                return response()->json([
                    'error' => 'amount_exceeds_remaining',
                    'message' => "El monto solicitado (\${$requestedAmount}) excede el pendiente (\${$bill->remaining_amount}).",
                    'remaining' => (float) $bill->remaining_amount,
                ], 422);
            }
            $amountToPay = $requestedAmount;
        } else {
            $amountToPay = (float) $bill->remaining_amount;
        }

        $paymentMethod = PaymentMethod::forBranch($branchId)
            ->where('uuid', $validated['payment_method_uuid'])
            ->firstOrFail();

        $cashSession = CashSession::where('branch_id', $branchId)
            ->where('status', CashSessionStatus::OPEN)
            ->first();

        $tipAmount = (float) ($validated['tip_amount'] ?? 0);

        try {
            $payment = $this->paymentService->registerPayment(
                order: $bill->order,
                paymentMethod: $paymentMethod,
                amount: $amountToPay,
                idempotencyKey: $validated['idempotency_key'],
                bill: $bill,
                cashSession: $cashSession,
                userId: $user->id,
                tipAmount: $tipAmount,
                referenceCode: $validated['reference_code'] ?? null,
                notes: $validated['notes'] ?? null
            );

            // Actualizar paid_amount del bill
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

                // Verificar si todos los orders de la mesa están paid
                $table = $order->table;
                if ($table) {
                    $activeOrdersCount = Order::where('table_id', $table->id)
                        ->where('branch_id', $branchId)
                        ->whereIn('status', [OrderStatus::DRAFT, OrderStatus::CONFIRMED, OrderStatus::PREPARING, OrderStatus::READY, OrderStatus::SERVED])
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
            }

            return response()->json([
                'data' => [
                    'success' => true,
                    'bill_uuid' => $bill->uuid,
                    'bill_paid' => $bill->status === BillStatus::PAID,
                    'paid_amount' => (float) $bill->paid_amount,
                    'remaining_amount' => (float) $bill->remaining_amount,
                    'order_transitioned_to_paid' => $orderTransitionedToPaid,
                    'amount_paid' => $amountToPay,
                    'tip_amount' => $tipAmount,
                ],
            ]);

        } catch (PaymentException $e) {
            return response()->json([
                'error' => 'payment_failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
