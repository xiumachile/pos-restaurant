<?php

namespace Modules\Identity\Domain\Services;

use Illuminate\Support\Facades\Hash;
use Modules\Identity\Domain\Entities\User;
use Modules\Identity\Domain\Exceptions\InvalidCredentialsException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AuthenticationService
{
    /**
     * TTL de la sesión POS efímera en segundos (5 minutos).
     */
    private const POS_SESSION_TTL = 300;

    /**
     * Prefijo para las claves de sesión POS en cache.
     */
    private const POS_SESSION_PREFIX = 'pos_session:';

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
        JWTAuth::parseToken()->invalidate();
    }

    /**
     * Construye la respuesta estándar de autenticación.
     */

    /**
     * Crea una sesión POS efímera a partir del PIN.
     * 
     * Este método se llama UNA SOLA VEZ durante el setup del POS.
     * Valida el PIN (operación O(n) aceptable en este contexto)
     * y genera un token efímero almacenado en cache con TTL corto.
     * 
     * @param int $branchId ID de la sucursal
     * @param string $pin PIN ingresado por el usuario
     * @return array Respuesta con session_token y expires_in
     * @throws InvalidCredentialsException Si el PIN no coincide
     */
    public function createPosSession(int $branchId, string $pin): array
    {
        // Validar PIN (operación O(n) aceptable en setup)
        $users = User::withoutGlobalScopes()->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereNotNull('pos_pin_hash')
            ->get();

        $matchedUser = $users->first(function ($user) use ($pin) {
            return $user->verifyPosPin($pin);
        });

        if (!$matchedUser) {
            throw InvalidCredentialsException::pin();
        }

        // Generar token efímero (32 caracteres hex)
        $sessionToken = bin2hex(random_bytes(16));

        // Almacenar en cache con TTL de 5 minutos
        // Clave: pos_session:{branch_id}:{token} → user_id
        $cacheKey = self::POS_SESSION_PREFIX . $branchId . ':' . $sessionToken;
        Cache::put($cacheKey, $matchedUser->id, self::POS_SESSION_TTL);

        return [
            'session_token' => $sessionToken,
            'token_type' => 'pos_session',
            'expires_in' => self::POS_SESSION_TTL,
            'branch_id' => $branchId,
            'user' => [
                'uuid' => $matchedUser->uuid,
                'name' => $matchedUser->name,
                'role' => $matchedUser->role,
            ],
        ];
    }

    /**
     * Autentica con token de sesión POS efímero.
     * 
     * Operación O(1): lookup directo en cache por branch_id + token.
     * El token es de UN SOLO USO: se elimina después del login exitoso.
     * 
     * @param int $branchId ID de la sucursal
     * @param string $sessionToken Token efímero generado por createPosSession
     * @return array Respuesta con token JWT y datos del usuario
     * @throws InvalidCredentialsException Si el token no existe o expiró
     */
    public function loginWithSessionToken(int $branchId, string $sessionToken): array
    {
        $cacheKey = self::POS_SESSION_PREFIX . $branchId . ':' . $sessionToken;
        
        // Lookup O(1) en cache
        $userId = Cache::get($cacheKey);

        if (!$userId) {
            throw InvalidCredentialsException::pin();
        }

        $user = User::withoutGlobalScopes()->find($userId);

        if (!$user || !$user->is_active) {
            Cache::forget($cacheKey);
            throw InvalidCredentialsException::inactive();
        }

        // Token de un solo uso: eliminar después de login exitoso
        Cache::forget($cacheKey);

        $token = JWTAuth::fromUser($user);

        return $this->buildResponse($user, $token);
    }

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
