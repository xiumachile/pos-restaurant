<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Exceptions\TenantContextNotSetException;
use App\Shared\Domain\Jobs\ProcessOrderJob;
use App\Shared\Domain\Events\OrderCreated;
use App\Shared\Domain\Listeners\SendOrderConfirmationEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->companyA = Company::create([
        'tax_id' => '76.111.111-1',
        'legal_name' => 'Company A SpA',
        'trade_name' => 'Company A',
    ]);

    $this->branchA = Branch::create([
        'company_id' => $this->companyA->id,
        'code' => 'BRA',
        'name' => 'Branch A',
    ]);

    $this->userA = User::create([
        'name' => 'User A',
        'email' => 'usera@test.com',
        'password' => 'password',
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'role' => 'cashier',
        'is_active' => true,
    ]);

    $this->tenantContext = app(TenantContext::class);
});

// ============================================
// Jobs en cola
// ============================================

test('Job sin contexto en entorno de test funciona (runningUnitTests permisivo)', function () {
    $order = Order::withoutGlobalScopes()->forceCreate([
        'uuid' => Str::uuid(),
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'waiter_id' => $this->userA->id,
        'order_number' => 'ORD-JOB-1',
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'total' => 1190,
    ]);

    $this->tenantContext->clear();

    expect(app()->runningUnitTests())->toBeTrue();
    
    $job = new ProcessOrderJob($order->id);
    expect(fn() => $job->handle())->not->toThrow(Exception::class);
});

test('Job con contexto de tenant funciona correctamente', function () {
    $order = Order::withoutGlobalScopes()->forceCreate([
        'uuid' => Str::uuid(),
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'waiter_id' => $this->userA->id,
        'order_number' => 'ORD-JOB-2',
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'total' => 1190,
    ]);

    $this->tenantContext->setCompany(
        companyId: $this->companyA->id,
        branchId: $this->branchA->id,
        userId: $this->userA->id,
        locale: 'es-CL',
        role: 'cashier',
        terminalId: null
    );

    $job = new ProcessOrderJob($order->id);
    expect(fn() => $job->handle())->not->toThrow(Exception::class);
});

test('Job dispatchado a cola preserva contexto en tests', function () {
    Bus::fake();

    $order = Order::withoutGlobalScopes()->forceCreate([
        'uuid' => Str::uuid(),
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'waiter_id' => $this->userA->id,
        'order_number' => 'ORD-JOB-3',
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'total' => 1190,
    ]);

    ProcessOrderJob::dispatch($order->id);
    Bus::assertDispatched(ProcessOrderJob::class);
});

test('CompanyScope tiene whitelist pública de comandos Artisan', function () {
    $allowedCommands = \App\Shared\Domain\Scopes\CompanyScope::ALLOWED_ARTISAN_COMMANDS;
    
    expect($allowedCommands)->toBeArray();
    expect($allowedCommands)->toContain('migrate');
    expect($allowedCommands)->toContain('db:seed');
    expect($allowedCommands)->toContain('cache:clear');
});

// ============================================
// Comandos Artisan custom
// ============================================

test('Comando Artisan sin --company falla con mensaje claro', function () {
    $this->artisan('reports:daily')
        ->expectsOutput('Debe proporcionar --company=ID')
        ->expectsOutput('Ejemplo: php artisan reports:daily --company=123')
        ->assertExitCode(1);
});

test('Comando Artisan con --company válido funciona', function () {
    Order::withoutGlobalScopes()->forceCreate([
        'uuid' => Str::uuid(),
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'waiter_id' => $this->userA->id,
        'order_number' => 'ORD-CMD-1',
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'total' => 1190,
    ]);

    $this->artisan('reports:daily', ['--company' => $this->companyA->id])
        ->expectsOutputToContain('Generando reporte para empresa')
        ->expectsOutputToContain('Total de órdenes hoy:')
        ->assertExitCode(0);
});

test('Comando Artisan con --company inválido falla con mensaje claro', function () {
    $this->artisan('reports:daily', ['--company' => 999999])
        ->expectsOutputToContain('Empresa con ID 999999 no encontrada')
        ->assertExitCode(1);
});

// ============================================
// Listeners
// ============================================

test('Listener síncrono con contexto funciona correctamente', function () {
    $order = Order::withoutGlobalScopes()->forceCreate([
        'uuid' => Str::uuid(),
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'waiter_id' => $this->userA->id,
        'order_number' => 'ORD-LISTENER-1',
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'total' => 1190,
    ]);

    $this->tenantContext->setCompany(
        companyId: $this->companyA->id,
        branchId: $this->branchA->id,
        userId: $this->userA->id,
        locale: 'es-CL',
        role: 'cashier',
        terminalId: null
    );

    $event = new OrderCreated($order);
    $listener = new SendOrderConfirmationEmail();

    expect(fn() => $listener->handle($event))->not->toThrow(Exception::class);
});

test('Listener asíncrono dispatchado a cola es registrado', function () {
    Event::fake();

    $order = Order::withoutGlobalScopes()->forceCreate([
        'uuid' => Str::uuid(),
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'waiter_id' => $this->userA->id,
        'order_number' => 'ORD-LISTENER-2',
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'total' => 1190,
    ]);

    event(new OrderCreated($order));
    Event::assertDispatched(OrderCreated::class);
});

// ============================================
// Migraciones y seeders
// ============================================

test('Migraciones pueden ejecutarse sin contexto de tenant', function () {
    $this->tenantContext->clear();
    $this->artisan('migrate:status')->assertExitCode(0);
});

test('Seeders están en la whitelist de comandos permitidos', function () {
    $allowedCommands = \App\Shared\Domain\Scopes\CompanyScope::ALLOWED_ARTISAN_COMMANDS;
    expect(in_array('db:seed', $allowedCommands))->toBeTrue();
});

// ============================================
// Tests unitarios
// ============================================

test('Tests unitarios pueden consultar sin contexto (runningUnitTests)', function () {
    expect(app()->runningUnitTests())->toBeTrue();
    $this->tenantContext->clear();
    $users = User::all();
    expect($users)->not->toBeNull();
});

test('withoutGlobalScopes siempre permite acceso sin contexto', function () {
    $companyB = Company::create([
        'tax_id' => '76.222.222-2',
        'legal_name' => 'Company B SpA',
        'trade_name' => 'Company B',
    ]);

    $branchB = Branch::create([
        'company_id' => $companyB->id,
        'code' => 'BRB',
        'name' => 'Branch B',
    ]);

    $userB = User::create([
        'name' => 'User B',
        'email' => 'userb@test.com',
        'password' => 'password',
        'company_id' => $companyB->id,
        'branch_id' => $branchB->id,
        'role' => 'cashier',
        'is_active' => true,
    ]);

    Order::withoutGlobalScopes()->forceCreate([
        'uuid' => Str::uuid(),
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'waiter_id' => $this->userA->id,
        'order_number' => 'ORD-SCOPE-A',
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'total' => 1190,
    ]);

    Order::withoutGlobalScopes()->forceCreate([
        'uuid' => Str::uuid(),
        'company_id' => $companyB->id,
        'branch_id' => $branchB->id,
        'waiter_id' => $userB->id,
        'order_number' => 'ORD-SCOPE-B',
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'subtotal' => 2000,
        'tax_amount' => 380,
        'total' => 2380,
    ]);

    $this->tenantContext->clear();
    $allOrders = Order::withoutGlobalScopes()->get();
    expect($allOrders->count())->toBe(2);
});

// ============================================
// Edge cases de TenantContext
// ============================================

test('TenantContext puede limpiarse y reutilizarse', function () {
    $this->tenantContext->setCompany(
        companyId: $this->companyA->id,
        branchId: $this->branchA->id,
        userId: $this->userA->id,
        locale: 'es-CL',
        role: 'cashier',
        terminalId: null
    );

    expect($this->tenantContext->hasCompany())->toBeTrue();
    $this->tenantContext->clear();
    expect($this->tenantContext->hasCompany())->toBeFalse();

    $companyC = Company::create([
        'tax_id' => '76.333.333-3',
        'legal_name' => 'Company C SpA',
        'trade_name' => 'Company C',
    ]);

    $this->tenantContext->setCompany(
        companyId: $companyC->id,
        branchId: null,
        userId: null,
        locale: 'es-CL',
        role: 'admin',
        terminalId: null
    );

    expect($this->tenantContext->companyId())->toBe($companyC->id);
});

test('Consultas anidadas preservan contexto', function () {
    $this->tenantContext->setCompany(
        companyId: $this->companyA->id,
        branchId: $this->branchA->id,
        userId: $this->userA->id,
        locale: 'es-CL',
        role: 'cashier',
        terminalId: null
    );

    $order = Order::withoutGlobalScopes()->forceCreate([
        'uuid' => Str::uuid(),
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'waiter_id' => $this->userA->id,
        'order_number' => 'ORD-NESTED-1',
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'total' => 1190,
    ]);

    $loadedOrder = Order::with('waiter.branch.company')->find($order->id);
    
    expect($loadedOrder)->not->toBeNull();
    expect($loadedOrder->waiter)->not->toBeNull();
    expect($loadedOrder->waiter->branch)->not->toBeNull();
    expect($loadedOrder->waiter->branch->company)->not->toBeNull();
    expect($loadedOrder->waiter->branch->company->id)->toBe($this->companyA->id);
});
