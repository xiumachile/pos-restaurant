<?php

use Illuminate\Support\Facades\Broadcast;
use Modules\Identity\Domain\Entities\User;

/**
 * Canales de Broadcasting para Sistema de Cocina (KDS)
 *
 * Con PusherBroadcaster, Laravel normaliza el nombre del canal
 * (quita el prefijo 'private-') antes de hacer match. Por eso
 * registramos SIN el prefijo, aunque el cliente envíe 'private-kitchen.{id}'.
 *
 * Flujo:
 *   Cliente envía: private-kitchen.123
 *   PusherBroadcaster::auth() → normalizeChannelName() → kitchen.123
 *   Match contra: kitchen.{branchId} → MATCH
 *   Callback valida rol + sucursal → true/false
 *
 * Reglas de autorización:
 * - kitchen.{branchId}: kitchen, admin, manager de la misma sucursal
 * - waiters.{branchId}: waiter, admin, manager de la misma sucursal
 * - dashboard.{companyId}: admin, manager de la misma empresa
 */

Broadcast::channel('kitchen.{branchId}', function (User $user, int $branchId) {
    if (!in_array($user->role, ['kitchen', 'admin', 'manager'], true)) {
        return false;
    }
    return (int) $user->branch_id === (int) $branchId;
});

Broadcast::channel('waiters.{branchId}', function (User $user, int $branchId) {
    if (!in_array($user->role, ['waiter', 'admin', 'manager'], true)) {
        return false;
    }
    return (int) $user->branch_id === (int) $branchId;
});

Broadcast::channel('dashboard.{companyId}', function (User $user, int $companyId) {
    if (!in_array($user->role, ['admin', 'manager'], true)) {
        return false;
    }
    return (int) $user->company_id === (int) $companyId;
});
