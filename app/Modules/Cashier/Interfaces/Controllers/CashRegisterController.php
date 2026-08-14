<?php

namespace Modules\Cashier\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Cashier\Domain\Entities\CashRegister;
use Modules\Cashier\Domain\Services\CashRegisterService;
use Modules\Cashier\Interfaces\Requests\CreateCashRegisterRequest;
use Modules\Cashier\Interfaces\Resources\CashRegisterResource;

class CashRegisterController extends Controller
{
    public function __construct(
        private CashRegisterService $registerService
    ) {}

    /**
     * GET /api/v1/cashier/registers
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = CashRegister::where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id);

        if ($request->query('active_only')) {
            $query->active();
        }
        if ($request->query('available_only')) {
            $query->available();
        }

        $registers = $query->orderBy('code')->get();

        return CashRegisterResource::collection($registers)->response();
    }

    /**
     * POST /api/v1/cashier/registers
     */
    public function store(CreateCashRegisterRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        try {
            $register = $this->registerService->create(
                $user->branch,
                $validated['name'],
                $validated['code'],
                $validated['opening_amount_default'] ?? 50000,
                $validated['max_amount'] ?? 500000,
                $validated['requires_dual_control'] ?? false,
                $validated['description'] ?? null
            );

            if (isset($validated['printer_id']) || isset($validated['drawer_serial'])) {
                $register->printer_id = $validated['printer_id'] ?? null;
                $register->drawer_serial = $validated['drawer_serial'] ?? null;
                $register->save();
            }

            return CashRegisterResource::make($register)
                ->response()
                ->setStatusCode(201);
        } catch (\Modules\Cashier\Domain\Exceptions\CashierException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * GET /api/v1/cashier/registers/{uuid}
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $register = CashRegister::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        return CashRegisterResource::make($register)->response();
    }

    /**
     * PATCH /api/v1/cashier/registers/{uuid}/toggle-active
     */
    public function toggleActive(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $register = CashRegister::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $active = $request->boolean('active', true);

        try {
            $updated = $this->registerService->toggleActive($register, $active);
            return CashRegisterResource::make($updated)->response();
        } catch (\Modules\Cashier\Domain\Exceptions\CashierException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
