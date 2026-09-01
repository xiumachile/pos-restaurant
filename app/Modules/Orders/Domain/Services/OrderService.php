<?php

namespace Modules\Orders\Domain\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderPriority;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\FulfillmentChannel;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Orders\Domain\Exceptions\OrderNotModifiableException;

/**
 * Servicio de gestión de pedidos.
 * Centraliza lógica de negocio de órdenes.
 */
class OrderService
{
    /**
     * Lista pedidos con filtros opcionales.
     */
    public function listOrders(int $branchId, array $filters = []): Collection
    {
        $query = Order::with(['items', 'table', 'waiter'])
            ->where('branch_id', $branchId);

        // Filtro por status
        if (!empty($filters['status']) && in_array($filters['status'], array_column(OrderStatus::cases(), 'value'))) {
            $query->where('status', OrderStatus::from($filters['status']));
        }

        // Filtro por mesa
        if (!empty($filters['table_uuid'])) {
            $query->whereHas('table', fn($sub) => $sub->where('uuid', $filters['table_uuid']));
        }

        // Filtro solo hoy
        if (!empty($filters['today_only'])) {
            $query->whereDate('created_at', today());
        }

        return $query
            ->orderBy('created_at', 'desc')
            ->limit($filters['limit'] ?? 50)
            ->get();
    }

    /**
     * Crea un nuevo pedido.
     */
    /**
     * Crea un pedido de cualquier tipo (dine_in, takeout, delivery).
     *
     * VALIDEZ DEL TIPO (defensa en profundidad):
     * - dine_in: REQUIERE table_uuid (validado también en CreateOrderRequest)
     * - takeout/delivery: NO pueden tener table_uuid (inconsistencia de dominio)
     *
     * CAMPOS DE FULFILLMENT:
     * - customer_name / customer_phone: identifican al cliente (takeout, delivery)
     * - pickup_at: hora programada de retiro (takeout)
     * - delivery_address / delivery_notes: datos de entrega (delivery)
     *
     * @throws \InvalidArgumentException Si las reglas de dominio son violadas
     */
    public function createOrder(int $branchId, int $companyId, int $waiterId, array $data): Order
    {
        $type = OrderType::from($data['type']);

        // ═══════════════════════════════════════════════════
        // VALIDACIÓN DE DOMINIO: reglas por tipo de pedido
        // ═══════════════════════════════════════════════════
        $tableUuid = $data['table_uuid'] ?? null;

        // dine_in REQUIERE mesa
        if ($type->requiresTable() && empty($tableUuid)) {
            throw new \InvalidArgumentException(
                'Los pedidos dine_in requieren una mesa asignada.'
            );
        }

        // takeout/delivery NO pueden tener mesa
        if ($type->forbidsTable() && !empty($tableUuid)) {
            throw new \InvalidArgumentException(
                "Los pedidos {$type->value} no pueden tener mesa asignada."
            );
        }

        // Resolver table_id si aplica
        $tableId = null;
        if (!empty($tableUuid)) {
            $table = RestaurantTable::where('uuid', $tableUuid)
                ->where('branch_id', $branchId)
                ->first();

            if (!$table) {
                throw new \InvalidArgumentException(
                    'La mesa especificada no existe o no pertenece a esta sucursal.'
                );
            }

            $tableId = $table->id;
        }

        $status = isset($data['status'])
            ? OrderStatus::from($data['status'])
            : OrderStatus::DRAFT;

        // Resolver fulfillment_channel:
        // - Si viene explícito en data, usarlo (ya validado en request)
        // - Si no, usar el canal por defecto del tipo de pedido
        $fulfillmentChannel = isset($data['fulfillment_channel'])
            ? FulfillmentChannel::from($data['fulfillment_channel'])
            : $type->defaultFulfillmentChannel();

        $order = Order::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'order_number' => $this->generateOrderNumber($branchId),
            'type' => $type,
            'fulfillment_channel' => $fulfillmentChannel,
            'status' => $status,
            'table_id' => $tableId,
            'waiter_id' => $waiterId,
            // Campos de fulfillment (opcionales, validados en request)
            'customer_name' => $data['customer_name'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'pickup_at' => $data['pickup_at'] ?? null,
            'delivery_address' => $data['delivery_address'] ?? null,
            'delivery_notes' => $data['delivery_notes'] ?? null,
            // Notas generales del pedido
            'notes' => $data['notes'] ?? null,
            // Totales inicializados en 0 (se calculan al agregar items)
            'subtotal' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 0,
        ]);

        $order->load(['items', 'table', 'waiter']);

        return $order;
    }

    /**
     * Actualiza un pedido existente.
     * 
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function updateOrder(string $uuid, int $companyId, array $data): Order
    {
        $order = Order::where('uuid', $uuid)
            ->where('company_id', $companyId)
            ->firstOrFail();

        if (!$order->isEditable()) {
            throw new OrderNotModifiableException();
        }

        // Actualizar mesa si se proporcionó
        if (array_key_exists('table_uuid', $data)) {
            $table = null;
            if ($data['table_uuid']) {
                $table = RestaurantTable::where('uuid', $data['table_uuid'])->first();
            }
            $order->table_id = $table?->id;
        }

        // Actualizar campos permitidos
        if (isset($data['status'])) {
            $order->status = OrderStatus::from($data['status']);

        // Si el pedido se confirma, verificar si la empresa tiene kitchen_display habilitado
        if ($order->status === OrderStatus::CONFIRMED) {
            $company = \Modules\Companies\Domain\Entities\Company::find($companyId);
            if (!$company->hasCapability('has_kitchen_display')) {
                // Si no tiene kitchen_display, marcar como ready directamente
                // (para barras, mostradores de café, etc.)
                $order->status = OrderStatus::READY;
            }
        }
        }

        if (isset($data['notes'])) {
            $order->notes = $data['notes'];
        }

        if (isset($data['guest_count'])) {
            $order->guest_count = $data['guest_count'];
        }

        $order->save();
        $order->load(['items', 'table', 'waiter']);

        return $order;
    }

    /**
     * Genera número de orden único para la sucursal/día.
     */
    public function generateOrderNumber(int $branchId): string
    {
        $date = now()->format('Ymd');
        $lastOrder = Order::where('branch_id', $branchId)
            ->whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        $seq = $lastOrder ? (intval(substr($lastOrder->order_number, -4)) + 1) : 1;

        return sprintf('ORD-%03d-%s-%04d', $branchId, $date, $seq);
    }
}
