<?php

namespace App\Shared\Domain\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    /**
     * Aplicar scope: filtra por company_id del usuario autenticado.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // No aplicar en consola (migraciones, seeders, comandos)
        if (app()->runningInConsole()) {
            return;
        }

        // No aplicar si no hay usuario autenticado
        if (!auth()->check()) {
            return;
        }

        $user = auth()->user();

        if (isset($user->company_id)) {
            $builder->where($model->getTable() . '.company_id', $user->company_id);
        }
    }
}
