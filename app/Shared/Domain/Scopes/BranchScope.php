<?php

namespace App\Shared\Domain\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Exceptions\TenantContextNotSetException;

/**
 * Scope global que aísla consultas por branch_id.
 * 
 * FAIL-CLOSED: Si no hay contexto de tenant disponible, lanza excepción
 * para prevenir fugas de datos entre sucursales.
 * 
 * NOTA: Este scope solo aplica a modelos que tienen branch_id.
 * Si el modelo no tiene columna branch_id, el scope no debe aplicarse
 * (esto se controla vía BelongsToTenant trait).
 */
class BranchScope implements Scope
{
    /**
     * Comandos Artisan que NO requieren contexto de tenant.
     */
    public const ALLOWED_ARTISAN_COMMANDS = [
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
        'cache:clear',
        'config:clear',
        'route:clear',
        'view:clear',
        'optimize:clear',
        'event:clear',
    ];

    public function apply(Builder $builder, Model $model): void
    {
        // Si el modelo no tiene columna branch_id, no aplicar
        if (!$this->modelHasBranchColumn($model)) {
            return;
        }

        /** @var TenantContext $tenantContext */
        $tenantContext = app(TenantContext::class);

        $branchId = null;

        if ($tenantContext->hasBranch()) {
            $branchId = $tenantContext->branchId();
        } elseif (auth()->check() && isset(auth()->user()->branch_id)) {
            $branchId = auth()->user()->branch_id;
        }

        if ($branchId !== null) {
            $builder->where(function($q) use ($model, $branchId) {
                $q->where($model->getTable() . '.branch_id', $branchId)
                  ->orWhereNull($model->getTable() . '.branch_id');
            });
            return;
        }

        // Sin contexto: verificar si es permitido
        if ($this->isInAllowedContext()) {
            return;
        }

        // FAIL-CLOSED
        throw new TenantContextNotSetException(
            modelClass: get_class($model)
        );
    }

    /**
     * Verifica si el modelo tiene columna branch_id.
     */
    private function modelHasBranchColumn(Model $model): bool
    {
        try {
            return in_array(
                'branch_id',
                \Illuminate\Support\Facades\Schema::getColumnListing($model->getTable())
            );
        } catch (\Exception $e) {
            // Si la tabla no existe (migración), no aplicar
            return false;
        }
    }

    private function isInAllowedContext(): bool
    {
        if (app()->runningUnitTests()) {
            return true;
        }

        if (app()->runningInConsole()) {
            $command = $this->getCurrentArtisanCommand();
            
            if ($command && in_array($command, self::ALLOWED_ARTISAN_COMMANDS, true)) {
                return true;
            }
        }

        return false;
    }

    private function getCurrentArtisanCommand(): ?string
    {
        if (isset($_SERVER['argv'][1])) {
            return $_SERVER['argv'][1];
        }
        
        return null;
    }
}
