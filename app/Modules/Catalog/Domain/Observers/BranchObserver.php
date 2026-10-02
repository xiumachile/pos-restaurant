<?php

namespace Modules\Catalog\Domain\Observers;

use Modules\Branches\Domain\Entities\Branch;
use Modules\Catalog\Application\Services\BranchDefaultProvisioner;

class BranchObserver
{
    public function __construct(
        protected BranchDefaultProvisioner $provisioner
    ) {}

    public function created(Branch $branch): void
    {
        $this->provisioner->ensureDefaults($branch);
    }
}
