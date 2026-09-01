<?php

namespace Modules\Identity\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\Domain\Entities\User;
use Modules\Identity\Domain\Exceptions\InvalidCredentialsException;
use Modules\Identity\Domain\Services\AuthenticationService;
use Modules\Identity\Interfaces\Requests\LoginRequest;
use Modules\Identity\Interfaces\Requests\PosLoginRequest;
use Modules\Identity\Interfaces\Requests\PosSessionRequest;
use Modules\Identity\Interfaces\Requests\RefreshRequest;

class AuthController extends Controller
{
    public function __construct(
        private AuthenticationService $authService
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $response = $this->authService->loginWithEmail(
                $request->email,
                $request->password
            );
            return response()->json($response);
        } catch (InvalidCredentialsException $e) {
            return response()->json([
                'error' => 'invalid_credentials',
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    /**
     * POST /api/v1/auth/pos-session
     * Crea una sesión POS efímera a partir del PIN.
     * Operación O(n) aceptable (solo se llama en setup).
     */
    public function posSession(PosSessionRequest $request): JsonResponse
    {
        try {
            $response = $this->authService->createPosSession(
                $request->branch_id,
                $request->pin
            );
            return response()->json($response, 201);
        } catch (InvalidCredentialsException $e) {
            return response()->json([
                'error' => 'invalid_credentials',
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    /**
     * POST /api/v1/auth/login/pos
     * Autentica con PIN (legacy) o session_token (nuevo, O(1)).
     * 
     * Backwards compatible: si se envía 'pin', usa el flujo legacy.
     * Si se envía 'session_token', usa el nuevo flujo O(1) con cache.
     */
    public function posLogin(PosLoginRequest $request): JsonResponse
    {
        try {
            // Nuevo flujo: session_token (O(1) lookup en cache)
            if ($request->has('session_token')) {
                $response = $this->authService->loginWithSessionToken(
                    $request->branch_id,
                    $request->session_token
                );
            } else {
                // Flujo legacy: PIN (O(n), mantener para compatibilidad)
                $response = $this->authService->loginWithPin(
                    $request->branch_id,
                    $request->pin
                );
            }
            return response()->json($response);
        } catch (InvalidCredentialsException $e) {
            return response()->json([
                'error' => 'invalid_credentials',
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    public function refresh(RefreshRequest $request): JsonResponse
    {
        try {
            $response = $this->authService->refresh();
            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'token_refresh_failed',
                'message' => 'No se pudo renovar el token.',
            ], 401);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $this->authService->logout();
            return response()->json([
                'message' => 'Sesión cerrada correctamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'logout_failed',
                'message' => 'No se pudo cerrar la sesión.',
            ], 400);
        }
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'locale' => $user->locale,
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
                'is_active' => $user->is_active,
            ],
        ]);
    }
}
