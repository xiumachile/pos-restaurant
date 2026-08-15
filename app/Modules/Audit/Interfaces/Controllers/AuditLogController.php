<?php

namespace Modules\Audit\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Audit\Domain\Entities\AuditLog;

/**
 * API de consulta de audit logs (solo lectura).
 * 
 * Solo accesible por admin y manager.
 */
class AuditLogController extends Controller
{
    /**
     * GET /api/v1/audit-logs
     * 
     * Lista audit logs con filtros.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()
            ->with(['user:id,name,email', 'branch:id,name'])
            ->orderBy('occurred_at', 'desc');

        // Filtros
        if ($request->filled('action')) {
            $query->action($request->input('action'));
        }

        if ($request->filled('user_id')) {
            $query->byUser($request->input('user_id'));
        }

        if ($request->filled('entity_type') && $request->filled('entity_id')) {
            $query->forEntity($request->input('entity_type'), $request->input('entity_id'));
        }

        if ($request->filled('date_from')) {
            $query->where('occurred_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('occurred_at', '<=', $request->input('date_to'));
        }

        $logs = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * GET /api/v1/audit-logs/{uuid}
     * 
     * Detalle de un audit log específico.
     */
    public function show(string $uuid): JsonResponse
    {
        $log = AuditLog::where('uuid', $uuid)
            ->with(['user:id,name,email', 'branch:id,name', 'company:id,trade_name'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $log,
        ]);
    }

    /**
     * GET /api/v1/audit-logs/actions
     * 
     * Lista de acciones disponibles.
     */
    public function actions(): JsonResponse
    {
        $actions = [
            'order_cancelled' => 'Orden cancelada',
            'discount_applied' => 'Descuento aplicado',
            'drawer_opened' => 'Cajón abierto',
            'price_changed' => 'Precio modificado',
        ];

        return response()->json([
            'success' => true,
            'data' => $actions,
        ]);
    }
}
