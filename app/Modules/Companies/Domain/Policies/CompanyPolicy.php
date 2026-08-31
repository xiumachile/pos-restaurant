<?php

namespace Modules\Companies\Domain\Policies;

use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;

/**
 * Policy para autorización de empresas a nivel de instancia.
 *
 * REGLAS DE AUTORIZACIÓN:
 * - super_admin: puede realizar cualquier operación sobre CUALQUIER empresa
 *   (necesario para gestión multi-tenant desde panel administrativo)
 * - admin: puede ver/editar/eliminar SOLO su propia empresa
 *   (defensa en profundidad: incluso si el scope BelongsToTenant funciona,
 *   validamos explícitamente aquí que company_id coincida)
 * - manager/cashier/waiter/kitchen: NO tienen acceso a operaciones de empresa
 *   (estas operaciones son de administración, no operativas)
 *
 * MÉTODOS:
 * - view: Ver detalle de empresa
 * - update: Actualizar datos de empresa
 * - delete: Soft-delete de empresa
 * - viewCapabilities: Ver capabilities de empresa
 * - updateCapabilities: Actualizar capabilities (toggle funcionalidades)
 *
 * NOTA: La creación de empresas (store) NO está en el policy porque se
 * protege a nivel de ruta con middleware 'role:super_admin'. El policy
 * solo opera sobre instancias existentes.
 */
class CompanyPolicy
{
    /**
     * Gate global: super_admin tiene acceso total.
     * Este método se ejecuta ANTES de cualquier otro check del policy.
     *
     * @return bool|null null = continuar evaluando, true = permitir, false = denegar
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === 'super_admin') {
            return true;
        }

        return null; // Continuar con los checks específicos
    }

    /**
     * Ver detalle de empresa.
     * Admin puede ver SOLO su propia empresa.
     */
    public function view(User $user, Company $company): bool
    {
        return $this->isAdminOfCompany($user, $company);
    }

    /**
     * Actualizar datos de empresa.
     * Admin puede editar SOLO su propia empresa.
     */
    public function update(User $user, Company $company): bool
    {
        return $this->isAdminOfCompany($user, $company);
    }

    /**
     * Eliminar empresa (soft delete).
     * Admin puede eliminar SOLO su propia empresa.
     */
    public function delete(User $user, Company $company): bool
    {
        return $this->isAdminOfCompany($user, $company);
    }

    /**
     * Ver capabilities de empresa.
     * Admin puede ver SOLO las capabilities de su propia empresa.
     */
    public function viewCapabilities(User $user, Company $company): bool
    {
        return $this->isAdminOfCompany($user, $company);
    }

    /**
     * Actualizar capabilities (toggle funcionalidades).
     * Admin puede editar SOLO las capabilities de su propia empresa.
     */
    public function updateCapabilities(User $user, Company $company): bool
    {
        return $this->isAdminOfCompany($user, $company);
    }

    /**
     * Valida que el usuario sea admin de la empresa especificada.
     *
     * Defensa en profundidad: incluso si BelongsToTenant scope filtra
     * queries automáticamente, validamos explícitamente aquí.
     */
    private function isAdminOfCompany(User $user, Company $company): bool
    {
        // Solo rol admin puede operar sobre empresas (no manager, cashier, etc.)
        if ($user->role !== 'admin') {
            return false;
        }

        // Admin solo puede operar sobre SU empresa
        return $user->company_id === $company->id;
    }
}
