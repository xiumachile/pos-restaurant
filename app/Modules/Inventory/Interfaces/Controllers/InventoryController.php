<?php

namespace Modules\Inventory\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Inventory\Domain\Entities\InventoryItem;
use Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use Modules\Inventory\Domain\Services\InventoryService;
use Modules\Inventory\Domain\ValueObjects\StockMovementType;
use Modules\Inventory\Domain\ValueObjects\StockStatus;
use Modules\Inventory\Interfaces\Requests\RecordMovementRequest;
use Modules\Inventory\Interfaces\Requests\StoreInventoryItemRequest;
use Modules\Inventory\Interfaces\Resources\InventoryItemResource;
use Modules\Inventory\Interfaces\Resources\StockMovementResource;

class InventoryController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService
    ) {}

    /**
     * GET /api/v1/inventory
     * Lista items de inventario del tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $query = InventoryItem::query();

        // Filtros
        if ($request->has('status')) {
            $status = StockStatus::from($request->input('status'));
            $branchId = $request->user()->branch_id;
            
            if ($status === StockStatus::LOW_STOCK) {
                $query->whereHas('stocks', function ($q) use ($branchId) {
                    $q->where('branch_id', $branchId)
                      ->whereColumn('quantity', '<=', \DB::raw('(SELECT min_stock FROM inventory_items WHERE inventory_items.id = inventory_stocks.inventory_item_id)'));
                });
            } elseif ($status === StockStatus::OUT_OF_STOCK) {
                $query->whereDoesntHave('stocks', function ($q) use ($branchId) {
                    $q->where('branch_id', $branchId)->where('quantity', '>', 0);
                });
            }
        }

        if ($request->boolean('active_only', false)) {
            $query->active();
        }

        $items = $query->orderBy('sku')->paginate($request->integer('per_page', 15));

        return InventoryItemResource::collection($items)->response();
    }

    /**
     * POST /api/v1/inventory
     * Crea un item de inventario.
     */
    public function store(StoreInventoryItemRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $item = InventoryItem::create([
            'company_id' => $user->company_id,
            'sku' => $validated['sku'] ?? null,
            'name_translations' => $validated['name_translations'],
            'unit' => $validated['unit'],
            'cost_price' => $validated['cost_price'] ?? 0,
            'min_stock' => $validated['min_stock'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return InventoryItemResource::make($item)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/inventory/{uuid}
     * Detalle de un item con su historial de movimientos.
     */
    public function show(string $uuid): JsonResponse
    {
        $item = InventoryItem::where('uuid', $uuid)->firstOrFail();

        return InventoryItemResource::make($item)->response();
    }

    /**
     * PUT /api/v1/inventory/{uuid}
     * Actualiza un item de inventario.
     */
    public function update(StoreInventoryItemRequest $request, string $uuid): JsonResponse
    {
        $item = InventoryItem::where('uuid', $uuid)->firstOrFail();
        $validated = $request->validated();

        $item->update($validated);

        return InventoryItemResource::make($item)->response();
    }

    /**
     * POST /api/v1/inventory/{uuid}/movement
     * Registra un movimiento de stock.
     */
    public function movement(RecordMovementRequest $request, string $uuid): JsonResponse
    {
        $item = InventoryItem::where('uuid', $uuid)->firstOrFail();
        $validated = $request->validated();
        $user = $request->user();

        $branch = Branch::where('uuid', $validated['branch_uuid'])->firstOrFail();

        try {
            $movement = $this->inventoryService->recordMovement(
                item: $item,
                branchId: $branch->id,
                type: StockMovementType::from($validated['type']),
                quantity: (float) $validated['quantity'],
                userId: $user->id,
                reason: $validated['reason'] ?? null
            );

            return StockMovementResource::make($movement)
                ->response()
                ->setStatusCode(201);
        } catch (InsufficientStockException $e) {
            return response()->json([
                'error' => 'insufficient_stock',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * GET /api/v1/inventory/{uuid}/movements
     * Historial de movimientos de un item.
     */
    public function movements(Request $request, string $uuid): JsonResponse
    {
        $item = InventoryItem::where('uuid', $uuid)->firstOrFail();

        $query = $item->movements()->with('user');

        if ($request->has('branch_uuid')) {
            $branch = Branch::where('uuid', $request->input('branch_uuid'))->first();
            if ($branch) {
                $query->where('branch_id', $branch->id);
            }
        }

        $movements = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return StockMovementResource::collection($movements)->response();
    }

    /**
     * GET /api/v1/inventory/alerts
     * Items con stock bajo o sin stock.
     */
    public function alerts(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;

        $items = InventoryItem::active()
            ->get()
            ->filter(function ($item) use ($branchId) {
                $status = $item->stockStatusForBranch($branchId);
                return $status !== \Modules\Inventory\Domain\ValueObjects\StockStatus::AVAILABLE;
            })
            ->values();

        return InventoryItemResource::collection($items)->response();
    }
}
