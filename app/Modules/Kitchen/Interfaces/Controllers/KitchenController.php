<?php

namespace Modules\Kitchen\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Kitchen\Domain\Services\KitchenQueueService;
use Modules\Kitchen\Interfaces\Requests\AssignCookRequest;
use Modules\Kitchen\Interfaces\Requests\UpdatePriorityRequest;
use Modules\Kitchen\Interfaces\Resources\KitchenOrderResource;

class KitchenController extends Controller
{
    public function __construct(
        private KitchenQueueService $queueService
    ) {}

    /**
     * GET /api/v1/kitchen/queue
     */
    public function queue(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;
        $queue = $this->queueService->getQueue($branchId);

        $response = $queue->map(function ($orders, $zone) {
            return [
                'zone' => $zone,
                'orders' => KitchenOrderResource::collection($orders)->resolve(),
                'count' => $orders->count(),
            ];
        })->values();

        return response()->json(['data' => $response]);
    }

    /**
     * GET /api/v1/kitchen/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;
        $stats = $this->queueService->getStats($branchId);

        return response()->json(['data' => $stats]);
    }

    /**
     * GET /api/v1/kitchen/history
     */
    public function history(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;
        $limit = $request->integer('limit', 50);
        $history = $this->queueService->getHistory($branchId, $limit);

        return KitchenOrderResource::collection($history)->response();
    }

    /**
     * GET /api/v1/kitchen/table-history/{tableUuid}
     */
    public function tableHistory(Request $request, string $tableUuid): JsonResponse
    {
        $branchId = $request->user()->branch_id;
        $data = $this->queueService->getTableHistory($branchId, $tableUuid);

        return response()->json([
            'data' => [
                'table' => $data['table'],
                'orders' => KitchenOrderResource::collection($data['orders'])->resolve(),
                'summary' => $data['summary'],
            ],
        ]);
    }

    /**
     * GET /api/v1/kitchen/tables-today
     */
    public function tablesToday(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;
        $tables = $this->queueService->getTablesToday($branchId);

        return response()->json(['data' => $tables]);
    }

    /**
     * POST /api/v1/kitchen/orders/{uuid}/assign-cook
     */
    public function assignCook(AssignCookRequest $request, string $uuid): JsonResponse
    {
        $validated = $request->validated();
        
        try {
            $order = $this->queueService->assignCookToOrder(
                $uuid,
                $validated['cook_uuid'],
                $request->user()->company_id
            );

            return KitchenOrderResource::make($order)->response();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'error' => 'invalid_state',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

    /**
     * POST /api/v1/kitchen/orders/{uuid}/priority
     */
    public function updatePriority(UpdatePriorityRequest $request, string $uuid): JsonResponse
    {
        $validated = $request->validated();
        
        try {
            $order = $this->queueService->updateOrderPriority(
                $uuid,
                $validated['priority'],
                $request->user()->company_id
            );

            return KitchenOrderResource::make($order)->response();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'error' => 'invalid_state',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }
}
