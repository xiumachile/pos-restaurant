<?php

namespace Modules\Cashier\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Cashier\Domain\Entities\CashCount;
use Modules\Cashier\Domain\Services\CashCountService;
use Modules\Cashier\Domain\ValueObjects\CashCountType;
use Modules\Cashier\Interfaces\Requests\CreateCashCountRequest;
use Modules\Cashier\Interfaces\Requests\SuperviseCashCountRequest;
use Modules\Cashier\Interfaces\Resources\CashCountResource;
use Modules\Payments\Domain\Entities\CashSession;

class CashCountController extends Controller
{
    public function __construct(
        private CashCountService $countService
    ) {}

    /**
     * GET /api/v1/cashier/counts
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = CashCount::where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->with(['user', 'supervisor'])
            ->orderByDesc('created_at');

        if ($sessionUuid = $request->query('session_uuid')) {
            $session = CashSession::where('uuid', $sessionUuid)->first();
            if ($session) {
                $query->where('cash_session_id', $session->id);
            }
        }
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($request->boolean('discrepancy_only')) {
            $query->where('has_discrepancy', true);
        }

        $limit = (int) $request->query('limit', 50);
        $counts = $query->limit(min($limit, 200))->get();

        return CashCountResource::collection($counts)->response();
    }

    /**
     * POST /api/v1/cashier/counts
     */
    public function store(CreateCashCountRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $session = CashSession::where('uuid', $validated['session_uuid'])->firstOrFail();
        $type = CashCountType::from($validated['type']);

        try {
            $count = match ($type) {
                CashCountType::OPENING => $this->countService->openingCount(
                    $session,
                    $user,
                    $validated['denominations'],
                    $validated['notes'] ?? null
                ),
                CashCountType::CLOSING => $this->countService->closingCount(
                    $session,
                    $user,
                    $validated['denominations'],
                    $validated['notes'] ?? null
                ),
                CashCountType::PARTIAL => $this->countService->partialCount(
                    $session,
                    $user,
                    $validated['denominations'],
                    $validated['reason'] ?? 'Arqueo parcial',
                    $validated['notes'] ?? null
                ),
                CashCountType::AUDIT => $this->countService->auditCount(
                    $session,
                    $user,
                    $validated['denominations'],
                    $validated['reason'] ?? 'Auditoría',
                    $validated['notes'] ?? null
                ),
            };

            $count->load(['user', 'supervisor']);

            return CashCountResource::make($count)
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
     * GET /api/v1/cashier/counts/{uuid}
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $count = CashCount::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->with(['user', 'supervisor', 'session'])
            ->firstOrFail();

        return CashCountResource::make($count)->response();
    }

    /**
     * POST /api/v1/cashier/counts/{uuid}/supervise
     */
    public function supervise(SuperviseCashCountRequest $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $count = CashCount::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        try {
            $supervised = $this->countService->superviseDiscrepancy(
                $count,
                $user,
                $validated['explanation']
            );

            $supervised->load(['user', 'supervisor']);

            return CashCountResource::make($supervised)->response();
        } catch (\Modules\Cashier\Domain\Exceptions\CashierException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
