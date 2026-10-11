<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create(['tax_id' => 'TEST-UX02', 'legal_name' => 'Test Co', 'trade_name' => 'Test']);
    $this->branch = Branch::create(['company_id' => $this->company->id, 'code' => 'T1', 'name' => 'Test Branch']);

    $this->user = User::create([
        'name' => 'Cajero',
        'email' => 'cajero@test.test',
        'password' => bcrypt('pw'),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);

    $this->token = JWTAuth::fromUser($this->user);
    
    $this->paymentMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => 'CASH',
        'name_translations' => ['es' => 'Efectivo'],
        'type' => 'cash',
        'is_active' => true,
        'requires_reference' => false,
    ]);
});

test('UX-02: Reintentar un pago con la misma idempotency_key devuelve el pago original sin duplicar', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-UX02-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::CONFIRMED,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    $idempotencyKey = Str::uuid()->toString();
    $payload = [
        'order_uuid' => $order->uuid,
        'payment_method_uuid' => $this->paymentMethod->uuid,
        'amount' => 11900,
        'idempotency_key' => $idempotencyKey,
    ];

    // Primer intento
    $response1 = $this->withHeaders(apiHeaders($this->token))->postJson('/api/v1/billing/payments', $payload);
    $response1->assertStatus(201);
    $payment1 = $response1->json('data');

    // Segundo intento (simula timeout del cliente, reenvía la MISMA idempotency_key)
    $response2 = $this->withHeaders(apiHeaders($this->token))->postJson('/api/v1/billing/payments', $payload);
    $response2->assertStatus(201);
    $payment2 = $response2->json('data');

    // Validar que es el MISMO pago (usando uuid)
    expect($payment1['uuid'])->toBe($payment2['uuid'], "El segundo intento debe devolver el mismo UUID de pago");
    expect($payment1['status'])->toBe('completed');
    
    // Verificar que solo hay 1 pago en la base de datos
    $paymentCount = \Modules\Payments\Domain\Entities\Payment::where('order_id', $order->id)->count();
    expect($paymentCount)->toBe(1, "No debe haber pagos duplicados en la base de datos");
});

test('UX-02: Reintentar con la misma idempotency_key pero payload diferente bloquea el cobro duplicado', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-UX02-002',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::CONFIRMED,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    $idempotencyKey = Str::uuid()->toString();
    
    // Primer intento exitoso
    $payload1 = [
        'order_uuid' => $order->uuid,
        'payment_method_uuid' => $this->paymentMethod->uuid,
        'amount' => 11900,
        'idempotency_key' => $idempotencyKey,
    ];
    $this->withHeaders(apiHeaders($this->token))->postJson('/api/v1/billing/payments', $payload1)->assertStatus(201);

    // Segundo intento con monto diferente (simula error o manipulación del cliente)
    $payload2 = [
        'order_uuid' => $order->uuid,
        'payment_method_uuid' => $this->paymentMethod->uuid,
        'amount' => 5000, // Monto diferente
        'idempotency_key' => $idempotencyKey, // Misma clave
    ];
    
    $response = $this->withHeaders(apiHeaders($this->token))->postJson('/api/v1/billing/payments', $payload2);
    
    // El backend bloquea esto con 422 Unprocessable Entity
    $response->assertStatus(422); 
    
    // Validar que el mensaje indica claramente el conflicto de idempotencia
    $response->assertJsonPath('error', 'payment_failed');
    $response->assertJsonFragment(['message' => "Conflicto de idempotencia: La clave '{$idempotencyKey}' ya existe con un payload diferente (monto, método o pedido distinto)."]);
    
    // Verificar que sigue habiendo solo 1 pago
    $paymentCount = \Modules\Payments\Domain\Entities\Payment::where('order_id', $order->id)->count();
    expect($paymentCount)->toBe(1, "No debe haber pagos duplicados ni corruptos en la base de datos");
});
