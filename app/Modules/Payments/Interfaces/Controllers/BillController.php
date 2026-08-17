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
        // DEBUG: Log del request ANTES de validación
        \Log::info('SPLIT REQUEST RAW', [
            'uuid' => $uuid,
            'raw_input' => $request->all(),
            'content_type' => $request->header('Content-Type'),
        ]);
        
        $validated = $request->validated();
        
        \Log::info('SPLIT REQUEST VALIDATED', [
            'validated' => $validated,
        ]);
        
        $order = Order::where('uuid', $uuid)->with('items')->firstOrFail();
        
        \Log::info('SPLIT ORDER LOADED', [
            'order_uuid' => $order->uuid,
            'order_total' => $order->total,
            'items_count' => $order->items->count(),
        ]);

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
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('SPLIT ValidationException', [
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ]);
            return response()->json([
                'error' => 'validation_failed',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('SPLIT Exception', [
                'message' => $e->getMessage(),
                'class' => get_class($e),
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
