<?php

namespace Modules\Identity\Domain\Services;

use Illuminate\Support\Facades\Hash;
use Modules\Identity\Domain\Entities\User;
use Modules\Identity\Domain\Exceptions\InvalidCredentialsException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthenticationService
{
    /**
     * Autentica con email + password (backoffice).
     */
    public function loginWithEmail(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw InvalidCredentialsException::email();
        }

        if (!$user->is_active) {
            throw InvalidCredentialsException::inactive();
        }

        $token = JWTAuth::fromUser($user);

        return $this->buildResponse($user, $token);
    }

    /**
     * Autentica con PIN POS + branch_id (terminales).
     */
    public function loginWithPin(int $branchId, string $pin): array
    {
        $user = User::where('branch_id', $branchId)->first();

        if (!$user || !$user->verifyPosPin($pin)) {
            throw InvalidCredentialsException::pin();
        }

        if (!$user->is_active) {
            throw InvalidCredentialsException::inactive();
        }

        $token = JWTAuth::fromUser($user);

        return $this->buildResponse($user, $token);
    }

    /**
     * Refresca el token JWT.
     */
    public function refresh(): array
    {
        $token = JWTAuth::parseToken()->refresh();
        $user = JWTAuth::setToken($token)->toUser();

        return $this->buildResponse($user, $token);
    }

    /**
     * Invalida el token actual (logout).
     */
    public function logout(): void
    {
        JWTAuth::parseToken()->invalidate();
    }

    /**
     * Construye la respuesta estándar de autenticación.
     */
    private function buildResponse(User $user, string $token): array
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'user' => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'locale' => $user->locale,
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
                'is_active' => $user->is_active,
            ],
        ];
    }
}
