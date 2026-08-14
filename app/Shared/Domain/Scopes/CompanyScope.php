<?php

namespace App\Shared\Domain\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Exceptions\TenantContextNotSetException;

/**
 * Scope global que aísla consultas por company_id.
 * 
 * FAIL-CLOSED: Si no hay contexto de tenant disponible, lanza excepción
 * para prevenir fugas de datos entre empresas.
 * 
 * Excepciones permitidas (fail-open):
 * - Tests unitarios (runningUnitTests)
 * - Migraciones y seeders (comandos específicos de Artisan)
 */
class CompanyScope implements Scope
{
    /**
     * Comandos Artisan que NO requieren contexto de tenant.
     * Solo operaciones de setup de BD.
     */
    private const ALLOWED_ARTISAN_COMMANDS = [
        'migrate',
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
        'migrate:status',
        'migrate:install',
        'migrate:clear',
        'db:seed',
        'db:wipe',
        // Comandos de limpieza (no acceden a datos)
        'cache:clear',
        'config:clear',
        'route:clear',
        'view:clear',
        'optimize:clear',
        'event:clear',
    ];

    public function apply(Builder $builder, Model $model): void
    {
        /** @var TenantContext $tenantContext */
        $tenantContext = app(TenantContext::class);

        // Intentar obtener company_id del contexto
        $companyId = null;

        if ($tenantContext->hasCompany()) {
            $companyId = $tenantContext->companyId();
        } elseif (auth()->check() && isset(auth()->user()->company_id)) {
            $companyId = auth()->user()->company_id;
        }

        // Si hay contexto, aplicar filtro normalmente
        if ($companyId !== null) {
            $builder->where($model->getTable() . '.company_id', $companyId);
            return;
        }

        // SIN CONTEXTO: Verificar si estamos en contexto permitido
        if ($this->isInAllowedContext()) {
            // Fail-open: no aplicar filtro (migraciones, tests, seeders)
            return;
        }

        // FAIL-CLOSED: Lanzar excepción
        throw new TenantContextNotSetException(
            modelClass: get_class($model)
        );
    }

    /**
     * Determina si el contexto actual permite ejecutar queries sin tenant.
     */
    private function isInAllowedContext(): bool
    {
        // 1. Tests unitarios (PHPUnit/Pest)
        if (app()->runningUnitTests()) {
            return true;
        }

        // 2. CLI con comandos específicos (migraciones/seeders)
        if (app()->runningInConsole()) {
            $command = $this->getCurrentArtisanCommand();
            
            if ($command && in_array($command, self::ALLOWED_ARTISAN_COMMANDS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtiene el comando Artisan actualmente en ejecución.
     */
    private function getCurrentArtisanCommand(): ?string
    {
        // argv[0] = artisan, argv[1] = comando
        if (isset($_SERVER['argv'][1])) {
            return $_SERVER['argv'][1];
        }
        
        return null;
    }
}
