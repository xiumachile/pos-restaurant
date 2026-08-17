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
        $validated = $request->validated();
        
        // DEBUG: Log completo del request
        \Log::info('SPLIT REQUEST', [
            'uuid' => $uuid,
            'validated' => $validated,
            'raw_input' => $request->all(),
        ]);
        
        $order = Order::where('uuid', $uuid)->with('items')->firstOrFail();

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
            \Log::error('SPLIT PaymentException', ['message' => $e->getMessage()]);
            return response()->json([
                'error' => 'split_failed',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('SPLIT Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
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
        $order = Order::where('uuid', $uuid)->firstOrFail();

        $bills = Bill::where('order_id', $order->id)
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('bill_number')
            ->get();

        return BillResource::collection($bills)->response();
    }
}
