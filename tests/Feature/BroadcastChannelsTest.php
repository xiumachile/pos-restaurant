<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Empresa A con dos sucursales
    $this->companyA = Company::create([
        'tax_id' => 'BROADCAST-A-' . uniqid(),
        'legal_name' => 'Broadcast Company A',
        'trade_name' => 'Broadcast A',
    ]);

    $this->branchA1 = Branch::create([
        'company_id' => $this->companyA->id,
        'code' => 'BR-A1',
        'name' => 'Branch A-1',
    ]);

    $this->branchA2 = Branch::create([
        'company_id' => $this->companyA->id,
        'code' => 'BR-A2',
        'name' => 'Branch A-2',
    ]);

    // Empresa B
    $this->companyB = Company::create([
        'tax_id' => 'BROADCAST-B-' . uniqid(),
        'legal_name' => 'Broadcast Company B',
        'trade_name' => 'Broadcast B',
    ]);

    $this->branchB = Branch::create([
        'company_id' => $this->companyB->id,
        'code' => 'BR-B',
        'name' => 'Branch B',
    ]);
});

function createBroadcastUser(string $role, $company, $branch): User
{
    return User::create([
        'name' => ucfirst($role) . ' ' . uniqid(),
        'email' => "{$role}-" . uniqid() . '@broadcast.test',
        'password' => 'password123',
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'role' => $role,
    ]);
}

// ============================================
// Canal kitchen.{branchId}
// ============================================

test('kitchen puede suscribirse a canal kitchen de su sucursal', function () {
    $kitchen = createBroadcastUser('kitchen', $this->companyA, $this->branchA1);
    $response = $this->actingAs($kitchen, 'api')
        ->postJson("/api/broadcasting/auth", [
            'channel_name' => 'private-kitchen.' . $this->branchA1->id,
            'socket_id' => '123.456789',
        ]);

    $response->assertOk();
});

test('admin puede suscribirse a canal kitchen de su sucursal', function () {
    $admin = createBroadcastUser('admin', $this->companyA, $this->branchA1);
    $response = $this->actingAs($admin, 'api')
        ->postJson("/api/broadcasting/auth", [
            'channel_name' => 'private-kitchen.' . $this->branchA1->id,
            'socket_id' => '123.456789',
        ]);

    $response->assertOk();
});

test('waiter NO puede suscribirse a canal kitchen', function () {
    $waiter = createBroadcastUser('waiter', $this->companyA, $this->branchA1);
    $response = $this->actingAs($waiter, 'api')
        ->postJson("/api/broadcasting/auth", [
            'channel_name' => 'private-kitchen.' . $this->branchA1->id,
            'socket_id' => '123.456789',
        ]);

    $response->assertStatus(403);
});

test('cashier NO puede suscribirse a canal kitchen', function () {
    $cashier = createBroadcastUser('cashier', $this->companyA, $this->branchA1);
    $response = $this->actingAs($cashier, 'api')
        ->postJson("/api/broadcasting/auth", [
            'channel_name' => 'private-kitchen.' . $this->branchA1->id,
            'socket_id' => '123.456789',
        ]);

    $response->assertStatus(403);
});

test('kitchen NO puede suscribirse a canal kitchen de otra sucursal', function () {
    $kitchen = createBroadcastUser('kitchen', $this->companyA, $this->branchA1);
    $response = $this->actingAs($kitchen, 'api')
        ->postJson("/api/broadcasting/auth", [
            'channel_name' => 'private-kitchen.' . $this->branchA2->id,
            'socket_id' => '123.456789',
        ]);

    $response->assertStatus(403);
});

test('kitchen NO puede suscribirse a canal kitchen de otra empresa', function () {
    $kitchen = createBroadcastUser('kitchen', $this->companyA, $this->branchA1);
    $response = $this->actingAs($kitchen, 'api')
        ->postJson("/api/broadcasting/auth", [
            'channel_name' => 'private-kitchen.' . $this->branchB->id,
            'socket_id' => '123.456789',
        ]);

    $response->assertStatus(403);
});

// ============================================
// Canal waiters.{branchId}
// ============================================

test('waiter puede suscribirse a canal waiters de su sucursal', function () {
    $waiter = createBroadcastUser('waiter', $this->companyA, $this->branchA1);
    $response = $this->actingAs($waiter, 'api')
        ->postJson("/api/broadcasting/auth", [
            'channel_name' => 'private-waiters.' . $this->branchA1->id,
            'socket_id' => '123.456789',
        ]);

    $response->assertOk();
});

test('admin puede suscribirse a canal waiters de su sucursal', function () {
    $admin = createBroadcastUser('admin', $this->companyA, $this->branchA1);
    $response = $this->actingAs($admin, 'api')
        ->postJson("/api/broadcasting/auth", [
            'channel_name' => 'private-waiters.' . $this->branchA1->id,
            'socket_id' => '123.456789',
        ]);

    $response->assertOk();
});

test('kitchen NO puede suscribirse a canal waiters', function () {
    $kitchen = createBroadcastUser('kitchen', $this->companyA, $this->branchA1);
    $response = $this->actingAs($kitchen, 'api')
        ->postJson("/api/broadcasting/auth", [
            'channel_name' => 'private-waiters.' . $this->branchA1->id,
            'socket_id' => '123.456789',
        ]);

    $response->assertStatus(403);
});

test('waiter NO puede suscribirse a canal waiters de otra sucursal', function () {
    $waiter = createBroadcastUser('waiter', $this->companyA, $this->branchA1);
    $response = $this->actingAs($waiter, 'api')
        ->postJson("/api/broadcasting/auth", [
            'channel_name' => 'private-waiters.' . $this->branchA2->id,
            'socket_id' => '123.456789',
        ]);

    $response->assertStatus(403);
});

// ============================================
// Canal dashboard.{companyId}
// ============================================

test('admin puede suscribirse a canal dashboard de su empresa', function () {
    $admin = createBroadcastUser('admin', $this->companyA, $this->branchA1);
    $response = $this->actingAs($admin, 'api')
        ->postJson("/api/broadcasting/auth", [
            'channel_name' => 'private-dashboard.' . $this->companyA->id,
            'socket_id' => '123.456789',
        ]);

    $response->assertOk();
});

test('manager puede suscribirse a canal dashboard de su empresa', function () {
    $manager = createBroadcastUser('manager', $this->companyA, $this->branchA1);
    $response = $this->actingAs($manager, 'api')
        ->postJson("/api/broadcasting/auth", [
            'channel_name' => 'private-dashboard.' . $this->companyA->id,
            'socket_id' => '123.456789',
        ]);

    $response->assertOk();
});

test('waiter NO puede suscribirse a canal dashboard', function () {
    $waiter = createBroadcastUser('waiter', $this->companyA, $this->branchA1);
    $response = $this->actingAs($waiter, 'api')
        ->postJson("/api/broadcasting/auth", [
            'channel_name' => 'private-dashboard.' . $this->companyA->id,
            'socket_id' => '123.456789',
        ]);

    $response->assertStatus(403);
});

test('kitchen NO puede suscribirse a canal dashboard', function () {
    $kitchen = createBroadcastUser('kitchen', $this->companyA, $this->branchA1);
    $response = $this->actingAs($kitchen, 'api')
        ->postJson("/api/broadcasting/auth", [
            'channel_name' => 'private-dashboard.' . $this->companyA->id,
            'socket_id' => '123.456789',
        ]);

    $response->assertStatus(403);
});

test('admin NO puede suscribirse a canal dashboard de otra empresa', function () {
    $admin = createBroadcastUser('admin', $this->companyA, $this->branchA1);
    $response = $this->actingAs($admin, 'api')
        ->postJson("/api/broadcasting/auth", [
            'channel_name' => 'private-dashboard.' . $this->companyB->id,
            'socket_id' => '123.456789',
        ]);

    $response->assertStatus(403);
});

// ============================================
// Canal sin autenticación
// ============================================

test('sin autenticación no se puede suscribir a canales privados', function () {
    $response = $this->postJson("/api/broadcasting/auth", [
        'channel_name' => 'private-kitchen.' . $this->branchA1->id,
        'socket_id' => '123.456789',
    ]);

    $response->assertStatus(401);
});

// ============================================
// Gaps completados S2 - Cross-tenant y roles faltantes
// ============================================

test('waiter NO puede suscribirse a canal waiters de otra empresa', function () {
    $waiter = createBroadcastUser('waiter', $this->companyA, $this->branchA1);
    $response = $this->actingAs($waiter, 'api')
        ->postJson("/api/broadcasting/auth", [
            'channel_name' => 'private-waiters.' . $this->branchB->id,
            'socket_id' => '123.456789',
        ]);

    $response->assertStatus(403);
});

test('cashier NO puede suscribirse a canal waiters', function () {
    $cashier = createBroadcastUser('cashier', $this->companyA, $this->branchA1);
    $response = $this->actingAs($cashier, 'api')
        ->postJson("/api/broadcasting/auth", [
            'channel_name' => 'private-waiters.' . $this->branchA1->id,
            'socket_id' => '123.456789',
        ]);

    $response->assertStatus(403);
});

test('manager NO puede suscribirse a canal dashboard de otra empresa', function () {
    $manager = createBroadcastUser('manager', $this->companyA, $this->branchA1);
    $response = $this->actingAs($manager, 'api')
        ->postJson("/api/broadcasting/auth", [
            'channel_name' => 'private-dashboard.' . $this->companyB->id,
            'socket_id' => '123.456789',
        ]);

    $response->assertStatus(403);
});

test('cashier NO puede suscribirse a canal dashboard', function () {
    $cashier = createBroadcastUser('cashier', $this->companyA, $this->branchA1);
    $response = $this->actingAs($cashier, 'api')
        ->postJson("/api/broadcasting/auth", [
            'channel_name' => 'private-dashboard.' . $this->companyA->id,
            'socket_id' => '123.456789',
        ]);

    $response->assertStatus(403);
});
