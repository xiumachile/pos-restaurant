<?php

namespace App\Shared\Domain\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\Shared\Application\TenantContext;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        /** @var TenantContext $tenantContext */
        $tenantContext = app(TenantContext::class);

        // Solo aplicar aislamiento si hay contexto configurado
        if ($tenantContext->hasCompany()) {
            $builder->where($model->getTable() . '.company_id', $tenantContext->companyId());
        } elseif (auth()->check() && isset(auth()->user()->company_id)) {
            $builder->where($model->getTable() . '.company_id', auth()->user()->company_id);
        }
        // Sin contexto: no aplicar filtro (migraciones, seeders)
    }
}
