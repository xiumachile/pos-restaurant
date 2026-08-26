<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\ModuleServiceProvider::class,
    App\Providers\OrderEventServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
    Modules\Tables\Providers\EventServiceProvider::class,
    Modules\Payments\Providers\PaymentsServiceProvider::class,
    Modules\Branches\Providers\BranchesServiceProvider::class,
];
