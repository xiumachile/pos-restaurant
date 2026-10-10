<?php

namespace Modules\Payments\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Orders\Domain\Entities\Order;
use Modules\Payments\Domain\Entities\Bill;
use Modules\Payments\Domain\Exceptions\PaymentException;
use Modules\Payments\Domain\Services\BillingService;
use Modules\Payments\Interfaces\Requests\SplitBillRequest;
use Modules\Payments\Interfaces\Resources\BillResource;
use Modules\Payments\Interfaces\Requests\StoreBillRequest;

class BillController extends Controller
{
    public function __construct(
        private BillingService $billingService
    ) {}

    public function split(SplitBillRequest $request, string $uuid): JsonResponse
    {
        if (!$request->user()->company->hasCapability('can_split_bills')) {
            return response()->json([
                'error' => 'capability_not_enabled',
                'message' => 'La empresa no tiene habilitada la funcionalidad de dividir cuentas',
                'required_capability' => 'can_split_bills',
            ], 403);
        }

        $validated = $request->validated();
        
        $order = Order::where('uuid', $uuid)
            ->where('company_id', $request->user()->company_id)
            ->with('items')
            ->firstOrFail();

        try {
            $type = $validated['type'];

            if ($type === 'equal_split') {
                $bills = $this->billingService->splitEqual($order, (int) $validated['parts']);
            } elseif ($type === 'by_items') {
                $bills = $this->billingService->splitByItems($order, $validated['groups']);
            } else {
                $bills = $this->billingService->splitByAmounts($order, $validated['amounts']);
            }

            return BillResource::collection($bills)->response();
        } catch (PaymentException $e) {
            return response()->json([
                'error' => 'split_failed',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'validation_failed',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'split_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function index(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $order = Order::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $bills = Bill::where('order_id', $order->id)
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('bill_number')
            ->get();

        return BillResource::collection($bills)->response();
    }

    /**
     * HALLAZGO CA-02: Sincroniza una bill desde frontend offline.
     * El servidor RECACLULA todos los montos basándose en la intención de división,
     * ignorando los campos 'client_*' enviados por el frontend para el cálculo financiero.
     */
    public function store(StoreBillRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // 1. Verificar idempotencia
        $existingBill = Bill::where('company_id', $user->company_id)
            ->where('idempotency_key', $validated['idempotency_key'])
            ->first();

        if ($existingBill) {
            return response()->json([
                'uuid' => $existingBill->uuid,
                'id' => $existingBill->id,
                'bill_number' => $existingBill->bill_number,
                'status' => $existingBill->status->value,
                'total' => $existingBill->total,
                'idempotent' => true,
            ], 200);
        }

        // 2. Obtener el order con sus items
        $order = Order::where('uuid', $validated['order_uuid'])
            ->where('company_id', $user->company_id)
            ->with('items')
            ->firstOrFail();

        try {
            $type = $validated['type'];
            $calculatedBills = [];

            if ($type === 'by_items') {
                $itemUuids = $validated['item_uuids'] ?? [];
                $itemIds = $order->items->whereIn('uuid', $itemUuids)->pluck('id')->toArray();
                
                $groups = [
                    [
                        'item_ids' => $itemIds,
                        'guest_count' => 1
                    ]
                ];
                $calculatedBills = $this->billingService->splitByItems($order, $groups);
            } elseif ($type === 'equal_split') {
                $parts = $validated['parts'] ?? 2;
                $calculatedBills = $this->billingService->splitEqual($order, $parts);
            } elseif ($type === 'custom_amount') {
                $amounts = [$validated['client_amount']];
                $calculatedBills = $this->billingService->splitByAmounts($order, $amounts);
            } else {
                $calculatedBills = [$this->billingService->createSingleBill($order)];
            }

            $bill = $calculatedBills[0];

            // HALLAZGO CA-02: Asignar idempotency_key a la bill calculada por el servidor
            if (!$bill->idempotency_key) {
                $bill->idempotency_key = $validated['idempotency_key'];
                $bill->save();
            }

            // 3. Logging de diagnóstico para detectar manipulaciones del cliente
            $clientTotal = $validated['client_total'] ?? null;
            if ($clientTotal !== null && (int) $clientTotal !== (int) $bill->total) {
                Log::warning('CA-02: Discrepancia en total de bill sincronizada', [
                    'order_uuid' => $order->uuid,
                    'idempotency_key' => $validated['idempotency_key'],
                    'client_total' => $clientTotal,
                    'server_total' => $bill->total,
                    'difference' => (int) $clientTotal - (int) $bill->total,
                ]);
            }

            return response()->json([
                'uuid' => $bill->uuid,
                'id' => $bill->id,
                'bill_number' => $bill->bill_number,
                'type' => $bill->type->value,
                'subtotal' => $bill->subtotal,
                'tax_amount' => $bill->tax_amount,
                'total' => $bill->total,
                'paid_amount' => $bill->paid_amount,
                'remaining_amount' => $bill->remaining_amount,
                'status' => $bill->status->value,
                'idempotent' => false,
                'message' => 'Bill creada con montos calculados por el servidor',
            ], 201);

        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), '23505') || str_contains($e->getMessage(), 'unique')) {
                $existingBill = Bill::where('company_id', $user->company_id)
                    ->where('idempotency_key', $validated['idempotency_key'])
                    ->first();
                
                if ($existingBill) {
                    return response()->json([
                        'uuid' => $existingBill->uuid,
                        'id' => $existingBill->id,
                        'bill_number' => $existingBill->bill_number,
                        'status' => $existingBill->status->value,
                        'total' => $existingBill->total,
                        'idempotent' => true,
                    ], 200);
                }
            }
            throw $e;
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'invalid_split_data',
                'message' => $e->getMessage(),
            ], 422);
        } catch (PaymentException $e) {
            return response()->json([
                'error' => 'payment_exception',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
