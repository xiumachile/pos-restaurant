<?php

namespace Modules\Payments\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Orders\Domain\Entities\Order;
use Modules\Payments\Domain\Entities\Bill;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\Exceptions\PaymentException;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;
use Modules\Payments\Domain\Services\PaymentService;
use Modules\Payments\Interfaces\Requests\StorePaymentRequest;
use Modules\Payments\Interfaces\Resources\PaymentResource;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {}

    /**
     * POST /api/v1/payments
     * Registra un pago para un pedido o bill específico.
     * Requiere header Idempotency-Key (según Arquitectura v1.1 Sección 12).
     */
    public function store(StorePaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // Si la empresa REQUIERE sesión de caja, verificar que exista una abierta.
        // La capability requires_cashier_session = true significa:
        // "no se pueden aceptar pagos sin una sesión de caja abierta previamente".
        // Si la capability está deshabilitada, los pagos son libres (sin control de caja).
        if ($user->company->hasCapability('requires_cashier_session')) {
            $openSession = CashSession::where('company_id', $user->company_id)
                ->where('branch_id', $user->branch_id)
                ->where('status', CashSessionStatus::OPEN)
                ->exists();

            if (!$openSession) {
                return response()->json([
                    'error' => 'cash_session_required',
                    'message' => 'Debe abrir una sesión de caja antes de aceptar pagos.',
                    'required_capability' => 'requires_cashier_session',
                ], 403);
            }
        }

        $order = Order::where('uuid', $validated['order_uuid'])
            ->where('company_id', $user->company_id)
            ->firstOrFail();
        $paymentMethod = PaymentMethod::forBranch($user->branch_id)->where('uuid', $validated['payment_method_uuid'])->firstOrFail();

        $bill = null;
        if (!empty($validated['bill_uuid'])) {
            $bill = Bill::where('uuid', $validated['bill_uuid'])
                ->where('company_id', $user->company_id)
                ->firstOrFail();
        }

        try {
            $payment = $this->paymentService->registerPayment(
                order: $order,
                paymentMethod: $paymentMethod,
                amount: (float) $validated['amount'],
                idempotencyKey: $validated['idempotency_key'],
                bill: $bill,
                cashSession: null, // Se maneja en F8 CASHIER
                userId: $user->id,
                tipAmount: (float) ($validated['tip_amount'] ?? 0),
                referenceCode: $validated['reference_code'] ?? null,
                notes: $validated['notes'] ?? null
            );

            return PaymentResource::make($payment)
                ->response()
                ->setStatusCode(201);
        } catch (PaymentException $e) {
            return response()->json([
                'error' => 'payment_failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
