<?php

namespace Modules\Payments\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /**
     * POST /api/v1/orders/{uuid}/split
     * Genera sub-cuentas según las 3 modalidades de Split Bill.
     * Según Arquitectura v1.1 Sección 11.3.
     */
    public function split(SplitBillRequest $request, string $uuid): JsonResponse
    {
        // Verificar que la empresa tenga habilitado can_split_bills
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
            } else { // custom_amount
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

    /**
     * GET /api/v1/orders/{uuid}/bills
     * Obtiene las sub-cuentas de un pedido.
     */
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
     * POST /api/v1/bills
     * 
     * ADR-020: Sincroniza una bill desde frontend offline.
     * 
     * Este endpoint permite al frontend offline sincronizar bills creadas localmente
     * (incluyendo split bills) al backend. El endpoint es idempotente vía idempotency_key.
     * 
     * Si la bill ya existe (por idempotency_key), retorna la bill existente sin crear duplicado.
     */
    public function store(StoreBillRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // Verificar idempotencia: si bill ya existe por idempotency_key, retornarla
        $existingBill = Bill::where('company_id', $user->company_id)
            ->where('idempotency_key', $validated['idempotency_key'])
            ->first();

        if ($existingBill) {
            return response()->json([
                'uuid' => $existingBill->uuid,
                'id' => $existingBill->id,
                'bill_number' => $existingBill->bill_number,
                'status' => $existingBill->status,
                'idempotent' => true,
            ], 200);
        }

        // Buscar order por UUID
        $order = Order::where('uuid', $validated['order_uuid'])
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        try {
            $bill = Bill::create([
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
                'order_id' => $order->id,
                'bill_number' => $validated['bill_number'],
                'type' => $validated['type'],
                'subtotal' => $validated['subtotal'],
                'tax_amount' => $validated['tax_amount'],
                'discount_amount' => $validated['discount_amount'],
                'tip_amount' => $validated['tip_amount'],
                'total' => $validated['total'],
                'paid_amount' => $validated['paid_amount'],
                'remaining_amount' => $validated['remaining_amount'],
                'status' => $validated['status'],
                'idempotency_key' => $validated['idempotency_key'],
            ]);

            return response()->json([
                'uuid' => $bill->uuid,
                'id' => $bill->id,
                'bill_number' => $bill->bill_number,
                'status' => $bill->status,
                'idempotent' => false,
            ], 201);

        } catch (\Illuminate\Database\QueryException $e) {
            // Manejar violación de unique constraint (idempotency_key duplicado)
            if (str_contains($e->getMessage(), 'Duplicate entry') || 
                str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                $existingBill = Bill::where('company_id', $user->company_id)
                    ->where('idempotency_key', $validated['idempotency_key'])
                    ->first();
                
                if ($existingBill) {
                    return response()->json([
                        'uuid' => $existingBill->uuid,
                        'id' => $existingBill->id,
                        'bill_number' => $existingBill->bill_number,
                        'status' => $existingBill->status,
                        'idempotent' => true,
                    ], 200);
                }
            }
            
            throw $e;
        }
    }
}
