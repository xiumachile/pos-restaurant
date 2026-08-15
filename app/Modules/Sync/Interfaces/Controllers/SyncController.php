<?php

namespace Modules\Sync\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sync\Domain\Services\SyncService;
use Modules\Sync\Domain\Services\SyncAdapter;
use Modules\Sync\Domain\Services\LocalDatabaseManager;
use Modules\Sync\Domain\Enums\ResolutionStrategy;

/**
 * API REST para sincronización offline-first.
 * 
 * Endpoints para que el cliente desktop (Tauri) sincronice
 * con el servidor central.
 */
class SyncController extends Controller
{
    protected SyncService $syncService;
    protected SyncAdapter $syncAdapter;

    public function __construct(SyncService $syncService, SyncAdapter $syncAdapter)
    {
        $this->syncService = $syncService;
        $this->syncAdapter = $syncAdapter;
    }

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

        $branchId = $validated['branch_id'];
        $limit = $validated['limit'] ?? 100;

        // Verificar que el usuario tenga acceso a esta sucursal
        $user = $request->user();
        if ($user->branch_id !== $branchId && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'error' => 'No tienes acceso a esta sucursal',
            ], 403);
        }

        $result = $this->syncService->pushChanges($branchId, $limit);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
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

        $branchId = $validated['branch_id'];
        $strategyValue = $validated['strategy'] ?? 'server_wins';
        $strategy = ResolutionStrategy::from($strategyValue);

        // Verificar acceso (casting a int para comparación correcta)
        $user = $request->user();
        if ((int) $user->branch_id !== (int) $branchId && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'error' => 'No tienes acceso a esta sucursal',
            ], 403);
        }

        $result = $this->syncService->pullChanges($branchId, $strategy);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * GET /api/v1/sync/status
     * 
     * Obtiene estadísticas de sincronización para una sucursal.
     */
    public function status(Request $request): JsonResponse
    {
        $branchId = $request->query('branch_id', $request->user()->branch_id);

        // Verificar acceso (casting a int para comparación correcta)
        $user = $request->user();
        if ((int) $user->branch_id !== (int) $branchId && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'error' => 'No tienes acceso a esta sucursal',
            ], 403);
        }

        $stats = $this->syncService->getSyncStats($branchId);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * GET /api/v1/sync/health
     * 
     * Verifica salud del sistema de sincronización.
     */
    public function health(): JsonResponse
    {
        $localDb = new LocalDatabaseManager();
        
        return response()->json([
            'success' => true,
            'data' => [
                'sync_service' => 'operational',
                'local_database' => $localDb->isAvailable() ? 'available' : 'unavailable',
                'local_database_size' => $localDb->getDatabaseSize(),
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
