<?php

namespace Modules\Orders\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Catalog\Domain\Entities\MenuItem;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Orders\Interfaces\Requests\AddItemRequest;
use Modules\Orders\Interfaces\Resources\OrderResource;

class OrderItemController extends Controller
{
    public function store(AddItemRequest $request, string $orderUuid): JsonResponse
    {
        $order = Order::where('uuid', $orderUuid)->firstOrFail();

        if (!$order->isEditable()) {
            return response()->json([
                'error' => 'order_not_modifiable',
                'message' => 'No se pueden agregar items a un pedido ya confirmado.',
            ], 422);
        }

        $validated = $request->validated();

        // Buscar el MenuItem (sin eager load para evitar problemas con Global Scopes)
        $menuItem = MenuItem::where('uuid', $validated['menu_item_uuid'])->firstOrFail();

        // Cargar el producto directamente usando withoutGlobalScopes
        // Esto evita que el Global Scope BelongsToTenant interfiera
        $product = Product::withoutGlobalScopes()->find($menuItem->product_id);

        if (!$product) {
            return response()->json([
                'error' => 'product_not_found',
                'message' => 'El producto asociado al item del menú no existe.',
            ], 422);
        }

        // Obtener nombre directamente del JSON de traducciones (español por defecto)
        $translations = $product->name_translations ?? [];
        if (is_string($translations)) {
            $translations = json_decode($translations, true) ?? [];
        }
        
        $productName = $translations['es'] ?? $translations['en'] ?? reset($translations) ?: 'Producto';

        // El precio efectivo es el del MenuItem o del Product como fallback
        $unitPrice = (float) ($menuItem->base_price ?? $product->base_price);

        $item = OrderItem::create([
            'company_id' => $order->company_id,
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'name_snapshot' => $productName,
            'unit_price_snapshot' => $unitPrice,
            'quantity' => $validated['quantity'],
            'notes' => $validated['notes'] ?? null,
            'subtotal' => $unitPrice * $validated['quantity'],
        ]);

        // Recalcular totales del pedido
        $order->recalculateTotals();
        $order->save();

        $order->load(['items.modifiers', 'table', 'waiter']);

        return OrderResource::make($order)
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(string $orderUuid, string $itemUuid): JsonResponse
    {
        $order = Order::where('uuid', $orderUuid)->firstOrFail();

        if (!$order->isEditable()) {
            return response()->json([
                'error' => 'order_not_modifiable',
                'message' => 'No se pueden quitar items de un pedido ya confirmado.',
            ], 422);
        }

        $item = OrderItem::where('uuid', $itemUuid)
            ->where('order_id', $order->id)
            ->firstOrFail();

        $item->delete();

        // Recalcular totales
        $order->recalculateTotals();
        $order->save();

        $order->load(['items.modifiers', 'table', 'waiter']);

        return OrderResource::make($order)->response();
    }
}
