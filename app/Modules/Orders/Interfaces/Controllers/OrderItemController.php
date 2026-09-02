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
        $order = Order::where('uuid', $orderUuid)
            ->where('company_id', $request->user()->company_id)
            ->firstOrFail();

        if (!$order->isEditable()) {
            return response()->json([
                'error' => 'order_not_modifiable',
                'message' => 'No se pueden agregar items a un pedido ya confirmado.',
            ], 422);
        }

        $validated = $request->validated();

        // Estrategia 1: Buscar por menu_item_uuid (flujo normal online)
        $menuItem = null;
        $product = null;

        if (!empty($validated['menu_item_uuid'])) {
            $menuItem = MenuItem::where('uuid', $validated['menu_item_uuid'])->first();
            if ($menuItem) {
                $product = Product::withoutGlobalScopes()->find($menuItem->product_id);
            }
        }

        // Estrategia 2: Buscar por product_uuid (flujo offline-first)
        // Busca el menu_item activo de esta sucursal para ese producto,
        // o usa el producto directamente si no hay menu_item.
        if (!$product && !empty($validated['product_uuid'])) {
            $product = Product::withoutGlobalScopes()
                ->where('uuid', $validated['product_uuid'])
                ->first();

            if ($product) {
                // Intentar encontrar un menu_item para este producto en la sucursal
                $menuItem = MenuItem::withoutGlobalScopes()
                    ->where('product_id', $product->id)
                    ->where('branch_id', $order->branch_id)
                    ->where('is_active', true)
                    ->first();
            }
        }

        if (!$product) {
            return response()->json([
                'error' => 'product_not_found',
                'message' => 'No se encontró el producto ni el item del menú.',
            ], 422);
        }

        // Obtener nombre del JSON de traducciones
        $translations = $product->name_translations ?? [];
        if (is_string($translations)) {
            $translations = json_decode($translations, true) ?? [];
        }
        $productName = $translations['es'] ?? $translations['en'] ?? reset($translations) ?: 'Producto';

        // Precio: menu_item > product (fallback)
        $unitPrice = (float) ($menuItem->base_price ?? $product->base_price);
        $subtotal = $unitPrice * $validated['quantity'];

        // Calcular impuesto: priorizar tax_rate del producto si está seteado,
        // sino usar la lógica de herencia (Product.tax → Category.tax → Default)
        if ($product->tax_rate !== null && $product->tax_rate > 0) {
            // Usar tax_rate directo del producto (más confiable en testing)
            $taxRate = (float) $product->tax_rate;
            $taxAmount = round($subtotal * ($taxRate / 100), 2);
            $taxName = null;
        } else {
            // Fallback a herencia
            $effectiveTax = $product->getEffectiveTax();
            $taxRate = $effectiveTax ? (float) $effectiveTax->rate : 0.0;
            $taxAmount = round($subtotal * ($taxRate / 100), 2);
            $taxName = $effectiveTax ? $effectiveTax->name : null;
        }

        // DEBUG: Ver valores calculados
        \Log::debug('OrderItemController::store', [
            'product_id' => $product->id,
            'product_tax_rate' => $product->tax_rate,
            'unitPrice' => $unitPrice,
            'quantity' => $validated['quantity'],
            'subtotal' => $subtotal,
            'taxRate' => $taxRate,
            'taxAmount' => $taxAmount,
        ]);

        $item = OrderItem::create([
            'product_id' => $product->id,
            'company_id' => $order->company_id,
            'order_id' => $order->id,
            'menu_item_id' => $menuItem?->id,  // nullable si no hay menu_item
            'name_snapshot' => $productName,
            'unit_price_snapshot' => $unitPrice,
            'quantity' => $validated['quantity'],
            'notes' => $validated['notes'] ?? null,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'tax_rate_snapshot' => $taxRate,
            'tax_name_snapshot' => $taxName,
        ]);

        $order->recalculateTotals();
        $order->save();

        $order->load(['items.modifiers', 'table', 'waiter']);

        return OrderResource::make($order)
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(string $orderUuid, string $itemUuid): JsonResponse
    {
        $order = Order::where('uuid', $orderUuid)
            ->where('company_id', request()->user()->company_id)
            ->firstOrFail();

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

        $order->recalculateTotals();
        $order->save();

        $order->load(['items.modifiers', 'table', 'waiter']);

        return OrderResource::make($order)->response();
    }
}
