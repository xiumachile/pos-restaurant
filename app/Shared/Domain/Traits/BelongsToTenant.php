<?php

namespace App\Shared\Domain\Traits;

use App\Shared\Domain\Scopes\CompanyScope;
use App\Shared\Domain\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    /**
     * Boot del trait: aplica Global Scopes de aislamiento.
     */
    protected static function bootBelongsToTenant(): void
    {
        // Scope de empresa (siempre aplicado)
        static::addGlobalScope(new CompanyScope());

        // Scope de sucursal (solo si la tabla tiene branch_id)
        if (static::hasBranchColumn()) {
            static::addGlobalScope(new BranchScope());
        }

        // Auto-asignar company_id y branch_id al crear
        static::creating(function ($model) {
            if (auth()->check()) {
                $user = auth()->user();
                
                if (empty($model->company_id) && isset($user->company_id)) {
                    $model->company_id = $user->company_id;
                }
                
                if (static::hasBranchColumn() && empty($model->branch_id) && isset($user->branch_id)) {
                    $model->branch_id = $user->branch_id;
                }
            }
        });
    }

    /**
     * Verificar si la tabla tiene columna branch_id.
     * 
     * Usa caché estática para evitar consultas repetidas a Schema.
     * Si la BD no está disponible (ej: durante composer install en CI),
     * retorna false para evitar errores en boot time.
     */
    protected static function hasBranchColumn(): bool
    {
        static $cache = [];
        $class = static::class;
        
        if (isset($cache[$class])) {
            return $cache[$class];
        }
        
        $instance = new static();
        
        // Chequeo rápido: si está en fillable, definitivamente existe
        if (in_array('branch_id', $instance->getFillable())) {
            return $cache[$class] = true;
        }
        
        // Chequeo de BD: puede fallar si la BD no existe todavía
        try {
            $cache[$class] = \Illuminate\Support\Facades\Schema::hasColumn($instance->getTable(), 'branch_id');
        } catch (\Throwable $e) {
            // BD no disponible (ej: composer install antes de migraciones)
            $cache[$class] = false;
        }
        
        return $cache[$class];
    }

    /**
     * Scope para filtrar por empresa específica.
     */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope para filtrar por sucursal específica.
     */
    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        if (!static::hasBranchColumn()) {
            return $query;
        }
        return $query->where('branch_id', $branchId);
    }
}
