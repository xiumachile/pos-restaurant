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
        $user = User::withoutGlobalScopes()->where('email', $email)->first();

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
     * 
     * Itera TODOS los usuarios activos de la sucursal y verifica el PIN
     * contra cada uno hasta encontrar coincidencia.
     * 
     * @param int $branchId ID de la sucursal
     * @param string $pin PIN ingresado por el usuario
     * @return array Respuesta con token JWT y datos del usuario
     * @throws InvalidCredentialsException Si el PIN no coincide con ningún usuario
     */
    public function loginWithPin(int $branchId, string $pin): array
    {
        // Obtener TODOS los usuarios activos de la sucursal con pos_pin_hash
        $users = User::withoutGlobalScopes()->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereNotNull('pos_pin_hash')
            ->get();

        // Iterar y verificar el PIN contra cada usuario
        $matchedUser = $users->first(function ($user) use ($pin) {
            return $user->verifyPosPin($pin);
        });

        if (!$matchedUser) {
            throw InvalidCredentialsException::pin();
        }

        $token = JWTAuth::fromUser($matchedUser);

        return $this->buildResponse($matchedUser, $token);
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
        // Forzar invalidación inmediata (ignora blacklist_grace_period)
        JWTAuth::parseToken()->invalidate(true);
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
