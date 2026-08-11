<?php

namespace App\Shared\Infrastructure\Auth;

use Illuminate\Auth\EloquentUserProvider;

class JwtUserProvider extends EloquentUserProvider
{
    /**
     * Retrieve a user by their unique identifier.
     * Usa withoutGlobalScopes() para evitar filtrado por tenant durante autenticación JWT.
     * Esto es necesario porque el TenantContextMiddleware aún no se ha ejecutado
     * cuando JWT necesita recuperar el usuario desde el token.
     */
    public function retrieveById($identifier)
    {
        $model = $this->createModel();
        
        return $model->newModelQuery()
            ->withoutGlobalScopes()
            ->where($model->getAuthIdentifierName(), $identifier)
            ->first();
    }
}
