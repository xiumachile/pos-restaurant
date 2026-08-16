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

    public function posLogin(PosLoginRequest $request): JsonResponse
    {
        try {
            $response = $this->authService->loginWithPin(
                $request->branch_id,
                $request->pin
            );
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
