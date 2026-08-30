<?php

namespace Modules\Companies\Policies;

use Modules\Identity\Domain\Entities\User;
use Modules\Companies\Domain\Entities\Company;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Policy para autorización de operaciones en Company.
 * 
 * Reglas:
 * - super_admin: acceso universal a todas las empresas
 * - admin/manager: solo acceso a su propia empresa
 * - cashier/waiter/kitchen: sin acceso directo a Company endpoints
 */
class CompanyPolicy
{
    use HandlesAuthorization;

    /**
     * Determina si el usuario puede ver la empresa.
     */
    public function view(User $user, Company $company): bool
    {
        return $user->role === 'super_admin' || $company->id === $user->company_id;
    }

    /**
     * Determina si el usuario puede actualizar la empresa.
     */
    public function update(User $user, Company $company): bool
    {
        return $user->role === 'super_admin' || $company->id === $user->company_id;
    }

    /**
     * Determina si el usuario puede eliminar la empresa.
     */
    public function delete(User $user, Company $company): bool
    {
        return $user->role === 'super_admin' || $company->id === $user->company_id;
    }

    /**
     * Determina si el usuario puede ver capabilities de la empresa.
     */
    public function viewCapabilities(User $user, Company $company): bool
    {
        return $user->role === 'super_admin' || $company->id === $user->company_id;
    }

    /**
     * Determina si el usuario puede actualizar capabilities de la empresa.
     */
    public function updateCapabilities(User $user, Company $company): bool
    {
        return $user->role === 'super_admin' || $company->id === $user->company_id;
    }
}
