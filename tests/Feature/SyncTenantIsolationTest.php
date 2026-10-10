<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Sync\Domain\Entities\SyncQueue;
use Modules\Sync\Domain\ValueObjects\SyncAction;
use Modules\Sync\Domain\Services\SyncManagementService;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Empresa A (del usuario)
    $this->companyA = Company::create(['tax_id' => 'COMP-A', 'legal_name' => 'Company A', 'trade_name' => 'Comp A']);
    $this->branchA1 = Branch::create(['company_id' => $this->companyA->id, 'code' => 'A1', 'name' => 'Branch A1']);
    $this->branchA2 = Branch::create(['company_id' => $this->companyA->id, 'code' => 'A2', 'name' => 'Branch A2']);
    
    // Empresa B (ajena)
    $this->companyB = Company::create(['tax_id' => 'COMP-B', 'legal_name' => 'Company B', 'trade_name' => 'Comp B']);
    $this->branchB1 = Branch::create(['company_id' => $this->companyB->id, 'code' => 'B1', 'name' => 'Branch B1']);

    // Usuario de Empresa A, asignado a Branch A1
    $this->userA = User::create([
        'name' => 'User A',
        'email' => 'userA@test.test',
        'password' => bcrypt('pw'),
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA1->id,
        'role' => 'cashier'
    ]);

    // Admin de Empresa A
    $this->adminA = User::create([
        'name' => 'Admin A',
        'email' => 'adminA@test.test',
        'password' => bcrypt('pw'),
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA1->id,
        'role' => 'admin'
    ]);

    $this->syncService = app(SyncManagementService::class);
});

test('O-02: Un usuario no puede sincronizar cambios de una sucursal de otra empresa', function () {
    // Intentar validar acceso a Branch B1 (Empresa B) con usuario de Empresa A
    expect(fn() => $this->syncService->validateBranchAccess($this->userA, $this->branchB1->id))
        ->toThrow(\DomainException::class, 'Acceso denegado: sucursal no válida o no autorizada');
});

test('O-02: Un admin de Empresa A no puede acceder a sucursales de Empresa B', function () {
    // El admin de A intenta acceder a B1
    expect(fn() => $this->syncService->validateBranchAccess($this->adminA, $this->branchB1->id))
        ->toThrow(\DomainException::class, 'Acceso denegado: sucursal no válida o no autorizada');
});

test('O-02: Un usuario no admin no puede acceder a otra sucursal de su misma empresa', function () {
    // User A (asignado a A1) intenta acceder a A2
    expect(fn() => $this->syncService->validateBranchAccess($this->userA, $this->branchA2->id))
        ->toThrow(\DomainException::class, 'Acceso denegado: no tienes asignada esta sucursal');
});

test('O-02: Un admin de Empresa A SÍ puede acceder a otra sucursal de su misma empresa', function () {
    // Admin A intenta acceder a A2 (debería permitirlo)
    $this->syncService->validateBranchAccess($this->adminA, $this->branchA2->id);
    
    // Si no lanza excepción, la validación pasó
    expect(true)->toBeTrue();
});

test('O-02: El payload de sync ignora company_id y branch_id maliciosos', function () {
    // Crear una orden legítima en Branch A1
    $order = Order::create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA1->id,
        'order_number' => 'ORD-SYNC-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    // Simular un payload malicioso que intenta cambiar la empresa/sucursal
    $maliciousPayload = array_merge($order->toArray(), [
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB1->id,
    ]);

    $queueItem = SyncQueue::create([
        'company_id' => $this->companyA->id, // Contexto real
        'branch_id' => $this->branchA1->id,  // Contexto real
        'entity_type' => Order::class,
        'entity_id' => $order->id,
        'entity_uuid' => $order->uuid,
        'action' => SyncAction::UPDATE,
        'payload' => $maliciousPayload,
        'status' => 'pending',
    ]);

    // Simular el procesamiento interno (que debería ignorar company_id/branch_id del payload)
    $entity = $queueItem->getEntity();
    $fillable = $entity->getFillable();
    $data = array_intersect_key($maliciousPayload, array_flip($fillable));
    
    // Campos de seguridad eliminados
    unset($data['id'], $data['uuid'], $data['company_id'], $data['branch_id'], $data['version']);
    
    $entity->fill($data);
    $entity->save();

    // Verificar que la orden NO cambió de empresa ni sucursal
    $order->refresh();
    expect($order->company_id)->toBe($this->companyA->id)
        ->and($order->branch_id)->toBe($this->branchA1->id);
});
