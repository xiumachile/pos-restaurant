<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\ModuleServiceProvider::class,
    App\Providers\OrderEventServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
    Modules\Tables\Providers\EventServiceProvider::class,
    Modules\Payments\Providers\PaymentsServiceProvider::class,
    Modules\Cashier\Providers\CashierServiceProvider::class,
    Modules\Branches\Providers\BranchesServiceProvider::class,
    Modules\Catalog\Providers\CatalogServiceProvider::class,
    Modules\Tables\Providers\TablesServiceProvider::class,
];
