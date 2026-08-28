<?php

namespace Modules\Sync\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sync\Domain\Enums\ResolutionStrategy;
use Modules\Sync\Domain\Services\SyncManagementService;

/**
 * API REST para sincronización offline-first.
 * 
 * Refactorizado en S5: toda la lógica de negocio delegada a SyncManagementService.
 * Este controller solo orquesta HTTP: valida inputs, delega al service, retorna JSON.
 */
class SyncController extends Controller
{
    public function __construct(
        private SyncManagementService $syncManagementService
    ) {}

    /**
     * POST /api/v1/sync/push
     * 
     * Cliente envía cambios locales al servidor.
     * Procesa la cola sync_queue y aplica cambios.
     */
    public function push(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $user = $request->user();
        $branchId = $validated['branch_id'];
        $limit = $validated['limit'] ?? 100;

        try {
            $this->syncManagementService->validateBranchAccess($user, $branchId);
            $result = $this->syncManagementService->pushChanges($branchId, $limit);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * POST /api/v1/sync/pull
     * 
     * Cliente descarga cambios del servidor.
     * Aplica cambios a entidades locales.
     */
    public function pull(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            'strategy' => 'nullable|string|in:server_wins,client_wins,merge,manual',
        ]);

        $user = $request->user();
        $branchId = $validated['branch_id'];
        $strategyValue = $validated['strategy'] ?? 'server_wins';
        $strategy = ResolutionStrategy::from($strategyValue);

        try {
            $this->syncManagementService->validateBranchAccess($user, $branchId);
            $result = $this->syncManagementService->pullChanges($branchId, $strategy);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * GET /api/v1/sync/status
     * 
     * Obtiene estadísticas de sincronización para una sucursal.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $request->query('branch_id', $user->branch_id);

        try {
            $this->syncManagementService->validateBranchAccess($user, (int) $branchId);
            $stats = $this->syncManagementService->getSyncStats((int) $branchId);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * GET /api/v1/sync/health
     * 
     * Verifica salud del sistema de sincronización.
     */
    public function health(): JsonResponse
    {
        $status = $this->syncManagementService->getHealthStatus();

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    /**
     * GET /api/v1/sync/changes
     * 
     * Retorna cambios incrementales desde last_pull_at.
     * Incluye registros soft-deleted para que el cliente los elimine localmente.
     * 
     * Query params:
     * - last_pull_at: ISO 8601 timestamp (opcional, si no se envía retorna todo)
     */
    public function changes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'last_pull_at' => 'nullable|date',
        ]);

        $user = $request->user();
        $companyId = $user->company_id;
        $branchId = $user->branch_id;

        $lastPullAt = isset($validated['last_pull_at']) 
            ? Carbon::parse($validated['last_pull_at']) 
            : null;

        $changes = $this->syncManagementService->getIncrementalChanges($companyId, $branchId, $lastPullAt);
        $totalChanges = array_sum(array_map('count', $changes));

        return response()->json([
            'success' => true,
            'data' => [
                'changes' => $changes,
                'total' => $totalChanges,
                'timestamp' => now()->toIso8601String(),
                'incremental' => $lastPullAt !== null,
            ],
        ]);
    }
}
