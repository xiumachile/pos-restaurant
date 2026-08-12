<?php

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de autorización por rol.
 *
 * Uso: ->middleware('role:admin,manager')
 * Permite acceso solo si el usuario tiene uno de los roles especificados.
 *
 * Roles disponibles: admin, manager, cashier, waiter, kitchen
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Sin usuario autenticado = 401
        if (!$user) {
            return response()->json([
                'error' => 'unauthenticated',
                'message' => 'Token de autenticación inválido o ausente.',
            ], 401);
        }

        // Verificar si el rol del usuario está en la lista permitida
        if (!in_array($user->role, $roles, true)) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'No tienes permisos para realizar esta acción.',
                'required_roles' => $roles,
                'current_role' => $user->role,
            ], 403);
        }

        return $next($request);
    }
}
