<?php

namespace App\Shared\Application;

class TenantContext
{
    private ?int $companyId = null;
    private ?int $branchId = null;
    private ?int $userId = null;
    private ?string $locale = null;
    private ?string $role = null;
    private ?int $terminalId = null;

    /**
     * Establece el contexto completo de tenant.
     */
    public function setCompany(
        ?int $companyId,
        ?int $branchId = null,
        ?int $userId = null,
        ?string $locale = null,
        ?string $role = null,
        ?int $terminalId = null
    ): void {
        $this->companyId = $companyId;
        $this->branchId = $branchId;
        $this->userId = $userId;
        $this->locale = $locale;
        $this->role = $role;
        $this->terminalId = $terminalId;
    }

    public function hasCompany(): bool
    {
        return $this->companyId !== null;
    }

    public function companyId(): ?int
    {
        return $this->companyId;
    }

    public function hasBranch(): bool
    {
        return $this->branchId !== null;
    }

    public function branchId(): ?int
    {
        return $this->branchId;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function locale(): ?string
    {
        return $this->locale;
    }

    public function role(): ?string
    {
        return $this->role;
    }

    public function terminalId(): ?int
    {
        return $this->terminalId;
    }

    /**
     * Limpia el contexto (útil para tests o logout).
     */
    public function clear(): void
    {
        $this->companyId = null;
        $this->branchId = null;
        $this->userId = null;
        $this->locale = null;
        $this->role = null;
        $this->terminalId = null;
    }
    /**
 * Establece solo la sucursal (útil para tests).
 */
	public function setBranch(?int $branchId): void
	{
   	 $this->branchId = $branchId;
	}	
}
