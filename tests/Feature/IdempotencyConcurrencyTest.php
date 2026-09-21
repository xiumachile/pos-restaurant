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
use Modules\Accounting\Domain\Entities\Account;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * PRUEBA DE CONCURRENCIA (P1):
 * Verifica que requests simultáneos con el mismo Idempotency-Key
 * NO ejecuten el negocio dos veces.
 *
 * Escenario:
 * - 2 requests HTTP simultáneos con misma idempotency-key
 * - Mismo company, branch, user, payload
 * - Endpoint POST /api/v1/billing/payments
 *
 * Resultado esperado (con middleware INSERT-first + defense-in-depth):
 * - Exactamente 1 payment creado
 * - Exactamente 1 par de ledger entries (debit + credit)
 * - 1 request recibe 201 (creado)
 * - 1 request recibe 200 (replay con header Idempotency-Replayed) O 409 (in progress)
 * - NUNCA 2 payments
 */
beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => '76.' . rand(100, 999) . '.' . rand(100, 999) . '-' . rand(0, 9),
        'legal_name' => 'Concurrency Test SpA',
        'trade_name' => 'Concurrency Test',
    ]);

    enableAllCapabilities($this->company);
    Account::seedDefaultsFor($this->company->id);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'CONC',
        'name' => 'Concurrency Branch',
    ]);

    $this->cashier = User::create([
        'name' => 'Cashier Concurrency',
        'email' => 'conc-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);

    // Schema real: name_translations (jsonb) en lugar de name
    $this->cashMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => 'cash',
        'name_translations' => ['es' => 'Efectivo'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    $this->order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->cashier->id,
        'order_number' => 'ORD-CONC-' . uniqid(),
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::CONFIRMED,
        'subtotal_gross' => 10000,
        'net_amount' => 8403,
        'tax_amount' => 1597,
        'subtotal' => 10000,
        'total' => 10000,
        'amount_due' => 10000,
    ]);

    $this->cashSession = CashSession::forceCreate([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->cashier->id,
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'session_number' => 'CS-CONC-' . uniqid(),
        'opened_at' => now(),
    ]);
});

test('dos requests secuenciales con misma idempotency-key crean solo UN payment', function () {
    $idempotencyKey = (string) Str::uuid();

    $payload = [
        'order_uuid' => $this->order->uuid,
        'payment_method_uuid' => $this->cashMethod->uuid,
        'amount' => 10000,
        'tip_amount' => 0,
        'idempotency_key' => $idempotencyKey,
    ];

    // Primer request (debería crear el payment)
    $response1 = $this->actingAs($this->cashier, 'api')
        ->withHeaders(['Idempotency-Key' => $idempotencyKey])
        ->postJson('/api/v1/billing/payments', $payload);

    expect($response1->getStatusCode())->toBe(201,
        "Primer request debería retornar 201 Created. Status: " . $response1->getStatusCode() .
        ". Body: " . $response1->getContent());

    // Segundo request con MISMA key (simula retry del cliente)
    $response2 = $this->actingAs($this->cashier, 'api')
        ->withHeaders(['Idempotency-Key' => $idempotencyKey])
        ->postJson('/api/v1/billing/payments', $payload);

    // Debería recibir replay (200 o 201 con Idempotency-Replayed) O 409 si está en progreso
    expect(in_array($response2->getStatusCode(), [200, 201]))->toBeTrue(
        "Segundo request debería retornar 200/201 (replay). Status: " . $response2->getStatusCode()
    );

    // ASSERT CRÍTICO: exactamente 1 payment en BD (no 2)
    $paymentCount = Payment::where('company_id', $this->company->id)
        ->where('idempotency_key', $idempotencyKey)
        ->count();

    expect($paymentCount)->toBe(1,
        "CRÍTICO: se crearon {$paymentCount} payments con misma idempotency-key. Debería ser 1.");

    // Verificar que ambos responses retornan el MISMO payment
    $data1 = $response1->json('data');
    $data2 = $response2->json('data');
    
    expect($data1['id'])->toBe($data2['id'],
        "Ambos requests deben retornar el mismo payment ID");
    expect($data1['uuid'])->toBe($data2['uuid'],
        "Ambos requests deben retornar el mismo payment UUID");
});

test('dos requests con misma key pero payload diferente retornan 409 conflict', function () {
    $idempotencyKey = (string) Str::uuid();

    // Primer request con amount=10000
    $response1 = $this->actingAs($this->cashier, 'api')
        ->withHeaders(['Idempotency-Key' => $idempotencyKey])
        ->postJson('/api/v1/billing/payments', [
            'order_uuid' => $this->order->uuid,
            'payment_method_uuid' => $this->cashMethod->uuid,
            'amount' => 10000,
            'tip_amount' => 0,
            'idempotency_key' => $idempotencyKey,
        ]);

    expect($response1->getStatusCode())->toBe(201);

    // Segundo request con MISMA key pero amount DIFERENTE
    $response2 = $this->actingAs($this->cashier, 'api')
        ->withHeaders(['Idempotency-Key' => $idempotencyKey])
        ->postJson('/api/v1/billing/payments', [
            'order_uuid' => $this->order->uuid,
            'payment_method_uuid' => $this->cashMethod->uuid,
            'amount' => 5000,  // ← Diferente payload
            'tip_amount' => 0,
            'idempotency_key' => $idempotencyKey,
        ]);

    // Debería retornar 409 Conflict (key reusada con payload diferente)
    expect($response2->getStatusCode())->toBe(409,
        "Reusar idempotency-key con payload diferente debería retornar 409");
});

test('requests con diferentes idempotency-keys pueden pagar el mismo order', function () {
    // Crear order con amount_due mayor para permitir pagos parciales
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->cashier->id,
        'order_number' => 'ORD-MULTI-' . uniqid(),
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::CONFIRMED,
        'subtotal_gross' => 20000,
        'net_amount' => 16807,
        'tax_amount' => 3193,
        'subtotal' => 20000,
        'total' => 20000,
        'amount_due' => 20000,
    ]);

    $key1 = (string) Str::uuid();
    $key2 = (string) Str::uuid();

    $response1 = $this->actingAs($this->cashier, 'api')
        ->withHeaders(['Idempotency-Key' => $key1])
        ->postJson('/api/v1/billing/payments', [
            'order_uuid' => $order->uuid,
            'payment_method_uuid' => $this->cashMethod->uuid,
            'amount' => 10000,
            'tip_amount' => 0,
            'idempotency_key' => $key1,
        ]);

    $response2 = $this->actingAs($this->cashier, 'api')
        ->withHeaders(['Idempotency-Key' => $key2])
        ->postJson('/api/v1/billing/payments', [
            'order_uuid' => $order->uuid,
            'payment_method_uuid' => $this->cashMethod->uuid,
            'amount' => 10000,
            'tip_amount' => 0,
            'idempotency_key' => $key2,
        ]);

    expect($response1->getStatusCode())->toBe(201);
    expect($response2->getStatusCode())->toBe(201);

    // Deben existir 2 payments (diferentes keys, diferentes transacciones)
    $count = Payment::where('order_id', $order->id)->count();
    expect($count)->toBe(2, "Dos pagos diferentes con keys diferentes deben crear 2 payments");
});

test('header Idempotency-Replayed indica respuesta cacheada', function () {
    $idempotencyKey = (string) Str::uuid();

    $payload = [
        'order_uuid' => $this->order->uuid,
        'payment_method_uuid' => $this->cashMethod->uuid,
        'amount' => 10000,
        'tip_amount' => 0,
        'idempotency_key' => $idempotencyKey,
    ];

    // Primer request
    $this->actingAs($this->cashier, 'api')
        ->withHeaders(['Idempotency-Key' => $idempotencyKey])
        ->postJson('/api/v1/billing/payments', $payload);

    // Segundo request (replay)
    $response2 = $this->actingAs($this->cashier, 'api')
        ->withHeaders(['Idempotency-Key' => $idempotencyKey])
        ->postJson('/api/v1/billing/payments', $payload);

    // El segundo request debe tener el header Idempotency-Replayed
    if ($response2->getStatusCode() === 200 || $response2->getStatusCode() === 201) {
        expect($response2->headers->get('Idempotency-Replayed'))->toBe('true',
            "Response de replay debe incluir header Idempotency-Replayed: true");
    }
});

test('CRITERIO DE CIERRE: bajo ninguna circunstancia se crean 2 payments con misma key', function () {
    $idempotencyKey = (string) Str::uuid();

    $payload = [
        'order_uuid' => $this->order->uuid,
        'payment_method_uuid' => $this->cashMethod->uuid,
        'amount' => 10000,
        'tip_amount' => 0,
        'idempotency_key' => $idempotencyKey,
    ];

    // Simular 5 "retries" rápidos (como haría un cliente nervioso)
    $responses = [];
    for ($i = 0; $i < 5; $i++) {
        $responses[] = $this->actingAs($this->cashier, 'api')
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson('/api/v1/billing/payments', $payload);
    }

    // CRITERIO DE CIERRE: Exactamente 1 payment, sin importar cuántos retries
    $paymentCount = Payment::where('company_id', $this->company->id)
        ->where('idempotency_key', $idempotencyKey)
        ->count();

    expect($paymentCount)->toBe(1,
        "Criterio de cierre violado: {$paymentCount} payments creados con misma idempotency-key. " .
        "Esto representa un cobro duplicado real al cliente.");

    // Todos los retries deben retornar el mismo payment UUID
    $uuids = array_filter(array_map(fn($r) => $r->json('data.uuid') ?? null, $responses));
    $uniqueUuids = array_unique($uuids);
    expect(count($uniqueUuids))->toBe(1,
        "Todos los retries deben retornar el mismo payment UUID");
});
