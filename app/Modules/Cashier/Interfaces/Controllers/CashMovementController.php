<?php

namespace Modules\Cashier\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Cashier\Domain\Entities\CashMovement;
use Modules\Cashier\Domain\Services\CashMovementService;
use Modules\Cashier\Domain\ValueObjects\MovementType;
use Modules\Cashier\Interfaces\Requests\CreateMovementRequest;
use Modules\Cashier\Interfaces\Resources\CashMovementResource;
use Modules\Identity\Domain\Entities\User;
use Modules\Payments\Domain\Entities\CashSession;

class CashMovementController extends Controller
{
    public function __construct(
        private CashMovementService $movementService
    ) {}

    /**
     * GET /api/v1/cashier/movements
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = CashMovement::where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->with(['user', 'authorizer'])
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

        $limit = (int) $request->query('limit', 50);
        $movements = $query->limit(min($limit, 200))->get();

        return CashMovementResource::collection($movements)->response();
    }

    /**
     * POST /api/v1/cashier/movements
     */
    public function store(CreateMovementRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $session = CashSession::where('uuid', $validated['session_uuid'])->firstOrFail();
        $type = MovementType::from($validated['type']);
        
        $authorizer = null;
        if (!empty($validated['authorizer_uuid'])) {
            $authorizer = User::where('uuid', $validated['authorizer_uuid'])->firstOrFail();
        }

        try {
            $movement = match ($type) {
                MovementType::WITHDRAWAL => $this->movementService->withdrawal(
                    $session,
                    $user,
                    (float) $validated['amount'],
                    $validated['reason'],
                    $validated['notes'] ?? null,
                    $authorizer
                ),
                MovementType::DEPOSIT => $this->movementService->deposit(
                    $session,
                    $user,
                    (float) $validated['amount'],
                    $validated['reason'],
                    $validated['notes'] ?? null
                ),
                MovementType::ADJUSTMENT => $this->movementService->adjustment(
                    $session,
                    $user,
                    (float) $validated['amount'],
                    $validated['reason'],
                    $authorizer,
                    $validated['notes'] ?? null
                ),
            };

            $movement->load(['user', 'authorizer']);

            return CashMovementResource::make($movement)
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
     * GET /api/v1/cashier/movements/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $sessionUuid = $request->query('session_uuid');
        
        if (!$sessionUuid) {
            return response()->json([
                'success' => false,
                'message' => 'El parámetro session_uuid es requerido.',
            ], 422);
        }

        $session = CashSession::where('uuid', $sessionUuid)->firstOrFail();
        $summary = $this->movementService->getSessionSummary($session);

        return response()->json(['data' => $summary]);
    }
}
