<?php

namespace Modules\Cashier\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Cashier\Domain\Services\CashierTableService;
use Modules\Cashier\Interfaces\Requests\ChargeTableRequest;
use Modules\Cashier\Interfaces\Requests\PayBillRequest;
use Modules\Payments\Domain\Entities\Bill;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\Exceptions\PaymentException;
use Modules\Payments\Interfaces\Resources\BillResource;
use Modules\Tables\Domain\Entities\RestaurantTable;

/**
 * Controller para operaciones de caja sobre mesas.
 * 
 * Refactorizado en S2: toda la lógica de negocio delegada a CashierTableService.
 * Este controller solo orquesta HTTP: valida inputs, delega al service, retorna JSON.
 */
class CashierTablesController extends Controller
{
    public function __construct(
        private CashierTableService $cashierTableService
    ) {}

    /**
     * GET /api/v1/cashier/tables-with-bills
     * Lista mesas con pedidos cobrables y sus datos completos.
     */
    public function tablesWithBills(\Illuminate\Http\Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;
        $response = $this->cashierTableService->getTablesWithBills($branchId);

        return response()->json(['data' => $response]);
    }

    /**
     * POST /api/v1/cashier/tables/{tableUuid}/prepare-bills
     * Crea una bill por cada order cobrable de la mesa.
     */
    public function prepareBills(\Illuminate\Http\Request $request, string $tableUuid): JsonResponse
    {
        $branchId = $request->user()->branch_id;

        $table = RestaurantTable::where('uuid', $tableUuid)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        try {
            $result = $this->cashierTableService->prepareBillsForTable($table, $branchId);

            return response()->json([
                'data' => [
                    'bills' => BillResource::collection($result['bills']),
                    'total_amount' => $result['total_amount'],
                    'orders_count' => $result['orders_count'],
                ],
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'error' => 'no_orders_to_charge',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/v1/cashier/tables/{tableUuid}/charge
     * Cobra todos los pedidos de una mesa con un método de pago.
     */
    public function chargeTable(ChargeTableRequest $request, string $tableUuid): JsonResponse
    {
        $user = $request->user();
        $branchId = $user->branch_id;
        $validated = $request->validated();

        $table = RestaurantTable::where('uuid', $tableUuid)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $paymentMethod = PaymentMethod::forBranch($branchId)
            ->where('uuid', $validated['payment_method_uuid'])
            ->firstOrFail();

        try {
            $result = $this->cashierTableService->chargeTable(
                table: $table,
                paymentMethod: $paymentMethod,
                data: $validated,
                user: $user,
                branchId: $branchId
            );

            return response()->json(['data' => $result]);
        } catch (PaymentException $e) {
            return response()->json([
                'error' => 'payment_failed',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\DomainException $e) {
            return response()->json([
                'error' => $this->mapDomainError($e->getMessage()),
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/v1/cashier/bills/{billUuid}/pay
     * Cobra una bill (sub-cuenta) específica.
     */
    public function payBill(PayBillRequest $request, string $billUuid): JsonResponse
    {
        $user = $request->user();
        $branchId = $user->branch_id;
        $validated = $request->validated();

        $bill = Bill::where('uuid', $billUuid)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $paymentMethod = PaymentMethod::forBranch($branchId)
            ->where('uuid', $validated['payment_method_uuid'])
            ->firstOrFail();

        try {
            $result = $this->cashierTableService->payBill(
                bill: $bill,
                paymentMethod: $paymentMethod,
                data: $validated,
                user: $user,
                branchId: $branchId
            );

            return response()->json(['data' => $result]);
        } catch (PaymentException $e) {
            return response()->json([
                'error' => 'payment_failed',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\DomainException $e) {
            return response()->json([
                'error' => $this->mapDomainError($e->getMessage()),
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Mapea mensajes de DomainException a códigos de error consistentes con la API.
     */
    private function mapDomainError(string $message): string
    {
        return match (true) {
            str_contains($message, 'no tiene pedidos') => 'no_orders_to_charge',
            str_contains($message, 'ya fue pagada') => 'bill_already_paid',
            str_contains($message, 'completamente pagada') => 'bill_fully_paid',
            str_contains($message, 'excede el pendiente') => 'amount_exceeds_remaining',
            str_contains($message, 'No autorizado') => 'unauthorized',
            default => 'domain_error',
        };
    }
}
