<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;
use Modules\Accounting\Domain\Entities\LedgerEntry;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * PRUEBA DE CONCURRENCIA (P1):
 * Verifica que requests simultáneos con el mismo Idempotency-Key
 * NO ejecuten el negocio dos veces.
 *
 * Escenario:
 * - 2 requests simultáneos con misma idempotency-key
 * - Mismo company, branch, user, payload
 * - Endpoint POST /api/v1/billing/payments
 *
 * Resultado esperado:
 * - Exactamente 1 payment creado
 * - Exactamente 1 ledger entry
 * - 1 request recibe 201 (creado)
 * - 1 request recibe 200 (replay) O 409 (conflict si aún en progreso)
 * - NUNCA 2 payments
 */
test('race condition: dos requests simultáneos con misma idempotency-key solo crean UN payment', function () {
    // Setup
    $company = Company::forceCreate([
        'tax_id' => '76.111.222-3',
        'legal_name' => 'Race Test SpA',
        'trade_name' => 'Race Test',
    ]);

    $branch = Branch::forceCreate([
        'company_id' => $company->id,
        'code' => 'RACE',
        'name' => 'Race Test Branch',
    ]);

    $user = User::forceCreate([
        'name' => 'Race User',
        'email' => 'race-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'role' => 'cashier',
    ]);

    PaymentMethod::forceCreate([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'code' => 'cash',
        'name' => 'Efectivo',
        'type' => 'cash',
        'is_active' => true,
    ]);

    $paymentMethod = PaymentMethod::where('company_id', $company->id)
        ->where('code', 'cash')
        ->first();

    $order = Order::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'waiter_id' => $user->id,
        'order_number' => 'ORD-RACE-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::CONFIRMED,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
        'amount_due' => 11900,
    ]);

    // Abrir sesión de caja
    $cashSession = CashSession::forceCreate([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'session_number' => 'CS-RACE-001',
        'opened_at' => now(),
    ]);

    $idempotencyKey = (string) Str::uuid();

    $payload = [
        'order_uuid' => $order->uuid,
        'payment_method_uuid' => $paymentMethod->uuid,
        'amount' => 11900,
        'tip_amount' => 0,
        'idempotency_key' => $idempotencyKey,
    ];

    // Ejecutar 2 requests "simultáneos" (en secuencia rápida, mismo proceso)
    // Esto simula la ventana de carrera del SELECT → INSERT
    $response1 = $this->actingAs($user, 'api')
        ->withHeaders(['Idempotency-Key' => $idempotencyKey])
        ->postJson('/api/v1/billing/payments', $payload);

    $response2 = $this->actingAs($user, 'api')
        ->withHeaders(['Idempotency-Key' => $idempotencyKey])
        ->postJson('/api/v1/billing/payments', $payload);

    // ASSERTS CRÍTICOS:
    
    // 1. Exactamente 1 payment en BD (no 2)
    $paymentCount = Payment::where('company_id', $company->id)
        ->where('idempotency_key', $idempotencyKey)
        ->count();
    
    expect($paymentCount)->toBe(1, 
        "Race condition detectada: se crearon {$paymentCount} payments con misma idempotency-key");

    // 2. El primer request debe ser 201 Created
    expect($response1->getStatusCode())->toBe(201);

    // 3. El segundo request debe ser 200 (replay) o 409 (conflict)
    expect(in_array($response2->getStatusCode(), [200, 201, 409]))->toBeTrue(
        "Segundo request retornó {$response2->getStatusCode()}, esperaba 200/201/409"
    );

    // 4. Exactamente 2 ledger entries (debit + credit del payment único)
    $ledgerCount = LedgerEntry::where('company_id', $company->id)
        ->where('reference_type', Payment::class)
        ->count();
    
    expect($ledgerCount)->toBe(2,
        "Race condition en ledger: {$ledgerCount} entries (debería ser 2: debit+credit)");
});

test('race condition real: pcntl_fork con dos procesos concurrentes', function () {
    if (!function_exists('pcntl_fork')) {
        $this->markTestSkipped('pcntl no disponible, saltando test de fork');
    }

    // Setup mínimo
    $company = Company::forceCreate([
        'tax_id' => '76.222.333-4',
        'legal_name' => 'Fork Test SpA',
        'trade_name' => 'Fork Test',
    ]);

    $branch = Branch::forceCreate([
        'company_id' => $company->id,
        'code' => 'FORK',
        'name' => 'Fork Test Branch',
    ]);

    $user = User::forceCreate([
        'name' => 'Fork User',
        'email' => 'fork-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'role' => 'cashier',
    ]);

    PaymentMethod::forceCreate([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'code' => 'cash',
        'name' => 'Efectivo',
        'type' => 'cash',
        'is_active' => true,
    ]);

    $order = Order::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'waiter_id' => $user->id,
        'order_number' => 'ORD-FORK-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::CONFIRMED,
        'subtotal' => 5000,
        'tax_amount' => 950,
        'total' => 5950,
        'amount_due' => 5950,
    ]);

    CashSession::forceCreate([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'session_number' => 'CS-FORK-001',
        'opened_at' => now(),
    ]);

    $idempotencyKey = (string) Str::uuid();
    $orderId = $order->id;
    $userId = $user->id;
    $companyId = $company->id;

    // Fork en 2 procesos que compiten
    $pid = pcntl_fork();

    if ($pid === -1) {
        $this->fail('No se pudo hacer fork');
    } elseif ($pid === 0) {
        // Proceso hijo
        $pm = PaymentMethod::where('company_id', $companyId)->where('code', 'cash')->first();
        $ord = Order::find($orderId);
        $usr = User::find($userId);
        
        $response = $this->actingAs($usr, 'api')
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson('/api/v1/billing/payments', [
                'order_uuid' => $ord->uuid,
                'payment_method_uuid' => $pm->uuid,
                'amount' => 5950,
                'idempotency_key' => $idempotencyKey,
            ]);
        
        exit($response->getStatusCode() === 201 ? 0 : 1);
    } else {
        // Proceso padre (ejecuta inmediatamente también)
        $pm = PaymentMethod::where('company_id', $companyId)->where('code', 'cash')->first();
        $ord = Order::find($orderId);
        $usr = User::find($userId);
        
        $response = $this->actingAs($usr, 'api')
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson('/api/v1/billing/payments', [
                'order_uuid' => $ord->uuid,
                'payment_method_uuid' => $pm->uuid,
                'amount' => 5950,
                'idempotency_key' => $idempotencyKey,
            ]);

        // Esperar al hijo
        pcntl_waitpid($pid, $status);

        // ASSERT: Exactamente 1 payment (no 2)
        $paymentCount = Payment::where('company_id', $companyId)
            ->where('idempotency_key', $idempotencyKey)
            ->count();

        expect($paymentCount)->toBe(1,
            "Race condition FORK: se crearon {$paymentCount} payments");
    }
});
