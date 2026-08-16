<?php

namespace Modules\Cashier\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Events\OrderPaid;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\Exceptions\PaymentException;
use Modules\Payments\Domain\Services\PaymentService;
use Modules\Tables\Domain\Entities\RestaurantTable;

class CashierTablesController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {}

    public function tablesWithBills(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;

        $tableIds = Order::where('branch_id', $branchId)
            ->where('status', OrderStatus::SERVED)
            ->whereNotNull('table_id')
            ->distinct()
            ->pluck('table_id');

        $tables = RestaurantTable::whereIn('id', $tableIds)
            ->orderBy('area_code')
            ->orderBy('table_number')
            ->get();

        $response = $tables->map(function ($table) use ($branchId) {
            $servedOrders = Order::where('table_id', $table->id)
                ->where('branch_id', $branchId)
                ->where('status', OrderStatus::SERVED)
                ->with(['items', 'waiter'])
                ->orderBy('created_at', 'asc')
                ->get();

            $totalAmount = $servedOrders->sum('total');
            $totalItems = $servedOrders->sum(fn($o) => $o->items->sum('quantity'));
            $totalTax = $servedOrders->sum('tax_amount');
            $totalSubtotal = $servedOrders->sum('subtotal');

            return [
                'table_uuid' => $table->uuid,
                'table_number' => $table->table_number,
                'area_code' => $table->area_code,
                'capacity' => $table->capacity,
                'orders_count' => $servedOrders->count(),
                'total_items' => $totalItems,
                'subtotal' => (float) $totalSubtotal,
                'tax_amount' => (float) $totalTax,
                'total_amount' => (float) $totalAmount,
                'first_order_at' => $servedOrders->first()?->created_at?->toIso8601String(),
                'last_order_at' => $servedOrders->last()?->created_at?->toIso8601String(),
                'orders' => $servedOrders->map(fn($order) => [
                    'uuid' => $order->uuid,
                    'order_number' => $order->order_number,
                    'status' => $order->status->value,
                    'subtotal' => (float) $order->subtotal,
                    'tax_amount' => (float) $order->tax_amount,
                    'total' => (float) $order->total,
                    'waiter_name' => $order->waiter?->name,
                    'served_at' => $order->served_at?->toIso8601String(),
                    'items' => $order->items->map(fn($item) => [
                        'uuid' => $item->uuid,
                        'name' => $item->name_snapshot,
                        'quantity' => $item->quantity,
                        'unit_price' => (float) $item->unit_price_snapshot,
                        'subtotal' => (float) $item->subtotal,
                        'notes' => $item->notes,
                    ])->values(),
                ])->values(),
            ];
        });

        return response()->json(['data' => $response]);
    }

    public function chargeTable(Request $request, string $tableUuid): JsonResponse
    {
        $user = $request->user();
        $branchId = $user->branch_id;

        $validated = $request->validate([
            'payment_method_uuid' => ['required', 'uuid', 'exists:payment_methods,uuid'],
            'tip_amount' => ['nullable', 'numeric', 'min:0'],
            'reference_code' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        $table = RestaurantTable::where('uuid', $tableUuid)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $servedOrders = Order::where('table_id', $table->id)
            ->where('branch_id', $branchId)
            ->where('status', OrderStatus::SERVED)
            ->orderBy('created_at', 'asc')
            ->get();

        if ($servedOrders->isEmpty()) {
            return response()->json([
                'error' => 'no_orders_to_charge',
                'message' => 'La mesa no tiene pedidos servidos para cobrar.',
            ], 422);
        }

        $paymentMethod = PaymentMethod::forBranch($branchId)
            ->where('uuid', $validated['payment_method_uuid'])
            ->firstOrFail();

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
                    cashSession: null,
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

            // ✅ FORZAR liberación de mesa con DB directo
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
}
