<?php

namespace App\Shared\Application;

class TenantContext
{
    protected ?int $companyId = null;
    protected ?int $branchId = null;
    protected ?int $terminalId = null;

    public function setCompany(?int $companyId): void
    {
        $this->companyId = $companyId;
    }

    public function setBranch(?int $branchId): void
    {
        $this->branchId = $branchId;
    }

    public function setTerminal(?int $terminalId): void
    {
        $this->terminalId = $terminalId;
    }

    public function companyId(): ?int
    {
        return $this->companyId;
    }

    public function branchId(): ?int
    {
        return $this->branchId;
    }

    public function terminalId(): ?int
    {
        return $this->terminalId;
    }

    public function hasCompany(): bool
    {
        return $this->companyId !== null;
    }

    public function hasBranch(): bool
    {
        return $this->branchId !== null;
    }
}
