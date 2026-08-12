<?php

use Illuminate\Support\Facades\Broadcast;
use Modules\Identity\Domain\Entities\User;

/*
|--------------------------------------------------------------------------
| Canales de Broadcasting para Sistema de Cocina (KDS)
|--------------------------------------------------------------------------
|
| Tres tipos de canales con aislamiento multi-tenant:
| - kitchen.{branch_id}: cocineros de la sucursal (kitchen, admin, manager)
| - waiters.{branch_id}: garzones de la sucursal (waiter, admin, manager)
| - dashboard.{company_id}: administradores de la empresa (admin, manager)
|
| El aislamiento se garantiza validando que el branch_id/company_id del
| usuario autenticado coincida con el del canal solicitado.
|
*/

/**
 * Canal de cocina: solo usuarios de esa sucursal con rol kitchen/admin/manager.
 */
Broadcast::channel('kitchen.{branchId}', function (User $user, int $branchId) {
    if (!in_array($user->role, ['kitchen', 'admin', 'manager'])) {
        return false;
    }
    return (int) $user->branch_id === (int) $branchId;
});

/**
 * Canal de garzones: solo usuarios de esa sucursal con rol waiter/admin/manager.
 */
Broadcast::channel('waiters.{branchId}', function (User $user, int $branchId) {
    if (!in_array($user->role, ['waiter', 'admin', 'manager'])) {
        return false;
    }
    return (int) $user->branch_id === (int) $branchId;
});

/**
 * Canal de dashboard: solo admins/managers de la empresa.
 */
Broadcast::channel('dashboard.{companyId}', function (User $user, int $companyId) {
    if (!in_array($user->role, ['admin', 'manager'])) {
        return false;
    }
    return (int) $user->company_id === (int) $companyId;
});
