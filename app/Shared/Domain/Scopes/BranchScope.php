<?php

namespace App\Shared\Domain\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\Shared\Application\TenantContext;

class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        /** @var TenantContext $tenantContext */
        $tenantContext = app(TenantContext::class);

        if ($tenantContext->hasBranch()) {
            $builder->where($model->getTable() . '.branch_id', $tenantContext->branchId());
        } elseif (auth()->check() && isset(auth()->user()->branch_id)) {
            $builder->where($model->getTable() . '.branch_id', auth()->user()->branch_id);
        }
    }
}
