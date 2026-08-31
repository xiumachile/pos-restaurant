<?php

namespace Modules\Payments\Domain\Policies;

use Modules\Identity\Domain\Entities\User;
use Modules\Payments\Domain\Entities\CashSession;

/**
 * Policy para autorización de operaciones de sesión de caja.
 * 
 * REGLAS DE AUTORIZACIÓN:
 * - open: Solo cashier/admin/manager pueden abrir sesión de caja
 * - close: Solo cashier/admin/manager pueden cerrar sesión, y debe pertenecer a su branch
 * - viewCurrent: Cualquier rol operativo puede ver la sesión actual de su branch
 * 
 * ROLES AUTORIZADOS:
 * - cashier: Operador de caja (puede abrir/cerrar)
 * - admin: Administrador de empresa (puede abrir/cerrar cualquier sesión de su branch)
 * - manager: Gerente de sucursal (puede abrir/cerrar cualquier sesión de su branch)
 * - waiter: NO puede operar sesiones de caja (solo puede ver la actual)
 * - kitchen: NO puede operar sesiones de caja (solo puede ver la actual)
 * 
 * DEFENSA EN PROFUNDIDAD:
 * Además del policy, el controller mantiene filtros de company_id y branch_id
 * en las queries para prevenir acceso cross-tenant/cross-branch incluso si
 * el policy falla o se omite.
 */
class CashSessionPolicy
{
    /**
     * Determina si el usuario puede abrir una nueva sesión de caja.
     */
    public function open(User $user): bool
    {
        return $this->isAuthorizedRole($user);
    }

    /**
     * Determina si el usuario puede cerrar una sesión de caja.
     * 
     * Valida:
     * 1. Rol autorizado (cashier/admin/manager)
     * 2. La sesión pertenece a la misma branch del usuario (cross-branch isolation)
     */
    public function close(User $user, CashSession $session): bool
    {
        if (!$this->isAuthorizedRole($user)) {
            return false;
        }

        return $this->belongsToUserBranch($user, $session);
    }

    /**
     * Determina si el usuario puede ver la sesión actual de su branch.
     * 
     * Cualquier rol operativo puede ver (cashier, waiter, kitchen, admin, manager).
     */
    public function viewCurrent(User $user): bool
    {
        return in_array($user->role, ['cashier', 'waiter', 'kitchen', 'admin', 'manager']);
    }

    /**
     * Valida que el usuario tenga un rol autorizado para operar caja.
     */
    private function isAuthorizedRole(User $user): bool
    {
        return in_array($user->role, ['cashier', 'admin', 'manager']);
    }

    /**
     * Valida que la sesión pertenezca a la misma branch del usuario.
     * Defensa en profundidad contra ataques cross-branch.
     */
    private function belongsToUserBranch(User $user, CashSession $session): bool
    {
        return $session->branch_id === $user->branch_id;
    }
}
