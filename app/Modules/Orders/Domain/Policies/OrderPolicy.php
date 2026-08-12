<?php

namespace Modules\Orders\Domain\Policies;

use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;

/**
 * Policy para autorización de pedidos a nivel de instancia.
 *
 * Solo verifica PERMISOS (rol + propiedad).
 * Las validaciones de transiciones las hace el OrderStateMachine.
 *
 * Reglas por rol:
 * - admin/manager: pueden hacer cualquier operación sobre cualquier pedido
 * - waiter: solo puede operar sobre SUS propios pedidos (waiter_id = user.id)
 * - kitchen: solo puede marcar preparing/ready
 * - cashier: solo puede marcar paid/close
 */
class OrderPolicy
{
    /**
     * Ver si el usuario puede ver el pedido.
     */
    public function view(User $user, Order $order): bool
    {
        if (in_array($user->role, ['admin', 'manager'])) {
            return true;
        }

        if ($user->role === 'waiter') {
            return $order->waiter_id === $user->id;
        }

        if ($user->role === 'kitchen') {
            return $order->status->isInKitchenQueue();
        }

        if ($user->role === 'cashier') {
            return $order->status->isAwaitingPayment();
        }

        return false;
    }

    /**
     * Modificar un pedido (agregar/quitar items, editar notas).
     * Solo admin/manager o dueño (waiter).
     * La validación de estado "draft" se hace en el controller/state machine.
     */
    public function update(User $user, Order $order): bool
    {
        if (in_array($user->role, ['admin', 'manager'])) {
            return true;
        }

        if ($user->role === 'waiter') {
            return $order->waiter_id === $user->id;
        }

        return false;
    }

    /**
     * Eliminar un pedido (solo draft).
     * La validación de estado draft se hace en el controller.
     */
    public function delete(User $user, Order $order): bool
    {
        return $this->update($user, $order);
    }

    /**
     * Confirmar pedido (draft → confirmed).
     * waiter solo puede confirmar sus propios pedidos.
     */
    public function confirm(User $user, Order $order): bool
    {
        if (in_array($user->role, ['admin', 'manager'])) {
            return true;
        }

        if ($user->role === 'waiter') {
            return $order->waiter_id === $user->id;
        }

        if ($user->role === 'cashier') {
            return true; // cashier puede confirmar (takeout)
        }

        return false;
    }

    /**
     * Marcar preparando (confirmed → preparing).
     * Solo kitchen, admin o manager.
     */
    public function prepare(User $user, Order $order): bool
    {
        return in_array($user->role, ['kitchen', 'admin', 'manager']);
    }

    /**
     * Marcar listo (preparing → ready).
     * Solo kitchen, admin o manager.
     */
    public function ready(User $user, Order $order): bool
    {
        return in_array($user->role, ['kitchen', 'admin', 'manager']);
    }

    /**
     * Marcar servido (ready → served).
     * Solo waiter (dueño), admin o manager.
     */
    public function serve(User $user, Order $order): bool
    {
        if (in_array($user->role, ['admin', 'manager'])) {
            return true;
        }

        if ($user->role === 'waiter') {
            return $order->waiter_id === $user->id;
        }

        return false;
    }

    /**
     * Marcar pagado (served → paid).
     * Solo cashier, admin o manager.
     */
    public function pay(User $user, Order $order): bool
    {
        return in_array($user->role, ['cashier', 'admin', 'manager']);
    }

    /**
     * Cerrar pedido (paid → closed).
     * Solo cashier, admin o manager.
     */
    public function close(User $user, Order $order): bool
    {
        return in_array($user->role, ['cashier', 'admin', 'manager']);
    }

    /**
     * Cancelar pedido.
     * waiter solo puede cancelar sus drafts.
     * manager/admin pueden cancelar cualquier pedido activo.
     * La validación de estado cancelable la hace el state machine.
     */
    public function cancel(User $user, Order $order): bool
    {
        if (in_array($user->role, ['admin', 'manager'])) {
            return true;
        }

        if ($user->role === 'waiter') {
            return $order->waiter_id === $user->id;
        }

        return false;
    }
}
