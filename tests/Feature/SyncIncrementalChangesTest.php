<?php

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Sync\Domain\Services\SyncManagementService;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create(['tax_id' => 'COMP-X', 'legal_name' => 'Company X', 'trade_name' => 'Comp X']);
    $this->branch = Branch::create(['company_id' => $this->company->id, 'code' => 'X1', 'name' => 'Branch X1']);
});

test('getIncrementalChanges ejecuta las 4 consultas sin error (regresión withTrashed en PaymentMethod)', function () {
    $result = app(SyncManagementService::class)
        ->getIncrementalChanges($this->company->id, $this->branch->id, Carbon::now()->subYear());

    expect($result)->toHaveKeys(['categories', 'products', 'tables', 'payment_methods']);
});

test('getIncrementalChanges funciona en la primera sincronización (sin fecha since)', function () {
    $result = app(SyncManagementService::class)
        ->getIncrementalChanges($this->company->id, $this->branch->id, null);

    expect($result)->toHaveKeys(['categories', 'products', 'tables', 'payment_methods']);
});
