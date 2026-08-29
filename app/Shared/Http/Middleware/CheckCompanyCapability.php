<?php

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de autorización por capability de empresa.
 *
 * Uso en rutas: ->middleware('capability:can_split_bills')
 *
 * Valida que la empresa del usuario autenticado tenga el capability
 * especificado habilitado. Si no lo tiene, retorna 403 Forbidden.
 *
 * Ejemplos de uso:
 *   ->middleware('capability:can_split_bills')
 *   ->middleware('capability:can_manage_inventory')
 *   ->middleware('capability:can_accept_tips')
 *
 * Super-admin siempre tiene acceso (no se valida capability para rol super_admin).
 */
class CheckCompanyCapability
{
    public function handle(Request $request, Closure $next, string $capabilityKey): Response
    {
        $user = $request->user();

        // Sin usuario autenticado = 401
        if (!$user) {
            return response()->json([
                'error' => 'unauthenticated',
                'message' => 'Token de autenticación inválido o ausente.',
            ], 401);
        }

        // Super-admin tiene acceso a todo (no se valida capability)
        if ($user->role === 'super_admin') {
            return $next($request);
        }

        // Obtener empresa del usuario
        $company = $user->company;

        if (!$company) {
            return response()->json([
                'error' => 'company_not_found',
                'message' => 'El usuario no está asociado a ninguna empresa.',
            ], 403);
        }

        // Validar que la empresa tenga el capability habilitado
        if (!$company->hasCapability($capabilityKey)) {
            return response()->json([
                'error' => 'capability_not_enabled',
                'message' => "Esta funcionalidad no está habilitada para tu empresa.",
                'required_capability' => $capabilityKey,
            ], 403);
        }

        return $next($request);
    }
}
