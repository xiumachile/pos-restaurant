<?php

namespace App\Shared\Domain\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BranchScope implements Scope
{
    /**
     * Aplicar scope: filtra por branch_id del usuario autenticado.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // No aplicar en consola
        if (app()->runningInConsole()) {
            return;
        }

        // No aplicar si no hay usuario autenticado
        if (!auth()->check()) {
            return;
        }

        $user = auth()->user();

        if (isset($user->branch_id)) {
            $builder->where($model->getTable() . '.branch_id', $user->branch_id);
        }
    }
}
