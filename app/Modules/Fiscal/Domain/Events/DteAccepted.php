<?php

namespace Modules\Fiscal\Domain\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Fiscal\Domain\Entities\DteDocument;

class DteAccepted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DteDocument $dte
    ) {}
}
