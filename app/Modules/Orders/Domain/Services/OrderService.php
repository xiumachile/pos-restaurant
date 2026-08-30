<?php

namespace Modules\Orders\Domain\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderPriority;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Tables\Domain\Entities\RestaurantTable;

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
    public function createOrder(int $branchId, int $companyId, int $waiterId, array $data): Order
    {
        $tableId = null;
        if (!empty($data['table_uuid'])) {
            $table = RestaurantTable::where('uuid', $data['table_uuid'])->first();
            $tableId = $table?->id;
        }

        $status = isset($data['status']) 
            ? OrderStatus::from($data['status']) 
            : OrderStatus::DRAFT;

        $order = Order::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'order_number' => $this->generateOrderNumber($branchId),
            'type' => OrderType::from($data['type']),
            'status' => $status,
            'table_id' => $tableId,
            'waiter_id' => $waiterId,
            'notes' => $data['notes'] ?? null,
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
            abort(422, 'No se pueden modificar pedidos ya confirmados.');
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
