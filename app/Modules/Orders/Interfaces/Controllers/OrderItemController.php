<?php

namespace Modules\Orders\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Domain\Entities\MenuItem;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Orders\Domain\Events\OrderItemRemoved;
use Modules\Orders\Interfaces\Requests\AddItemRequest;
use Modules\Orders\Interfaces\Resources\OrderResource;

class OrderItemController extends Controller
{
    public function store(AddItemRequest $request, string $orderUuid): JsonResponse
    {
        return DB::transaction(function () use ($request, $orderUuid) {
            $order = Order::where('uuid', $orderUuid)
                ->where('company_id', $request->user()->company_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorize('update', $order);

            if (!$order->isEditable()) {
                return response()->json([
                    'error' => 'order_not_modifiable',
                    'message' => 'No se pueden agregar items a un pedido ya confirmado.',
                ], 422);
            }

            $validated = $request->validated();
            $menuItem = null;
            $product = null;

            if (!empty($validated['menu_item_uuid'])) {
                $menuItem = MenuItem::where('uuid', $validated['menu_item_uuid'])
                    ->where('company_id', $order->company_id)
                    ->where('branch_id', $order->branch_id)
                    ->where('is_active', true)
                    ->first();

                if ($menuItem) {
                    $product = Product::where('id', $menuItem->product_id)
                        ->where('company_id', $order->company_id)
                        ->where('is_active', true)
                        ->first();
                }
            }

            if (!$product && !empty($validated['product_uuid'])) {
                $product = Product::where('uuid', $validated['product_uuid'])
                    ->where('company_id', $order->company_id)
                    ->where('is_active', true)
                    ->first();

                if ($product) {
                    $menuItem = MenuItem::where('product_id', $product->id)
                        ->where('company_id', $order->company_id)
                        ->where('branch_id', $order->branch_id)
                        ->where('is_active', true)
                        ->first();
                }
            }

            if (!$product) {
                return response()->json([
                    'error' => 'product_not_found',
                    'message' => 'No se encontró el producto o no está disponible en esta sucursal.',
                ], 422);
            }

            $translations = $product->name_translations ?? [];
            if (is_string($translations)) {
                $translations = json_decode($translations, true) ?? [];
            }
            $productName = $translations['es'] ?? $translations['en'] ?? reset($translations) ?: 'Producto';

            $unitPrice = (int) ($menuItem->base_price ?? $product->base_price);
            $subtotal = $unitPrice * $validated['quantity'];

            if ($product->tax_rate !== null && $product->tax_rate > 0) {
                $taxRate = (float) $product->tax_rate;
                $taxName = null;
            } else {
                $effectiveTax = $product->getEffectiveTax();
                $taxRate = $effectiveTax ? (float) $effectiveTax->rate : 0.0;
                $taxName = $effectiveTax ? $effectiveTax->name : null;
            }

            $item = OrderItem::create([
                'product_id' => $product->id,
                'company_id' => $order->company_id,
                'order_id' => $order->id,
                'menu_item_id' => $menuItem?->id,
                'name_snapshot' => $productName,
                'unit_price_snapshot' => $unitPrice,
                'quantity' => $validated['quantity'],
                'notes' => $validated['notes'] ?? null,
                'subtotal' => $subtotal,
                'tax_rate_snapshot' => $taxRate,
                'tax_name_snapshot' => $taxName,
            ]);

            $order->recalculateTotals();
            $order->save();

            $order->load(['items.modifiers', 'table', 'waiter']);

            return OrderResource::make($order)->response()->setStatusCode(201);
        });
    }

    public function destroy(Request $request, string $orderUuid, string $itemUuid): JsonResponse
    {
        return DB::transaction(function () use ($request, $orderUuid, $itemUuid) {
            $order = Order::where('uuid', $orderUuid)
                ->where('company_id', $request->user()->company_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorize('update', $order);

            if (!$order->isEditable()) {
                return response()->json([
                    'error' => 'order_not_modifiable',
                    'message' => 'No se pueden quitar items de un pedido ya confirmado.',
                ], 422);
            }

            $item = OrderItem::where('uuid', $itemUuid)
                ->where('order_id', $order->id)
                ->firstOrFail();

            // HALLAZGO M-04: Disparar evento de auditoría ANTES de eliminar el item
            // Se usa DB::afterCommit para garantizar que el log de auditoría solo se registre
            // si la transacción de eliminación se confirma exitosamente.
            DB::afterCommit(function () use ($order, $item, $request) {
                OrderItemRemoved::dispatch($order, $item, 'manual_removal', $request->user()->id);
            });

            $item->delete();

            $order->recalculateTotals();
            $order->save();

            $order->load(['items.modifiers', 'table', 'waiter']);

            return OrderResource::make($order)->response();
        });
    }
}
