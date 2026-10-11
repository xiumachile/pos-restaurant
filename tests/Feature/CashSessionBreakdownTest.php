<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;
use Modules\Payments\Domain\ValueObjects\PaymentStatus;
use Modules\Payments\Interfaces\Resources\CashSessionResource;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create(['tax_id' => 'TEST-UX04', 'legal_name' => 'Test Co', 'trade_name' => 'Test']);
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
    
    $this->cashMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'CASH',
        'name_translations' => ['es' => 'Efectivo'],
        'type' => 'cash',
        'is_active' => true,
    ]);
    
    $this->cardMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'CARD',
        'name_translations' => ['es' => 'Tarjeta'],
        'type' => 'card',
        'is_active' => true,
    ]);
});

test('UX-04: El recurso de sesión de caja incluye el desglose detallado de métodos de pago', function () {
    // 1. Abrir sesión de caja
    $session = CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->user->id,
        'session_number' => 'CS-001',
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
        'expected_amount' => 50000,
    ]);

    // 2. Crear pedidos
    $order1 = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-UX04-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::PAID,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    $order2 = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-UX04-002',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::PAID,
        'subtotal' => 20000,
        'tax_amount' => 3800,
        'total' => 23800,
    ]);

    // 3. Crear pagos usando el patrón correcto
    Payment::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->user->id,
        'order_id' => $order1->id,
        'cash_session_id' => $session->id,
        'payment_method_id' => $this->cashMethod->id,
        'payment_number' => 'PAY-TEST-001',
        'method_code' => 'CASH',
        'amount' => 11900,
        'tip_amount' => 1000,
        'total_amount' => 12900,
        'status' => PaymentStatus::COMPLETED,
        'idempotency_key' => Str::uuid()->toString(),
        'paid_at' => now(),
    ]);

    Payment::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->user->id,
        'order_id' => $order2->id,
        'cash_session_id' => $session->id,
        'payment_method_id' => $this->cardMethod->id,
        'payment_number' => 'PAY-TEST-002',
        'method_code' => 'CARD',
        'amount' => 23800,
        'tip_amount' => 2000,
        'total_amount' => 25800,
        'status' => PaymentStatus::COMPLETED,
        'idempotency_key' => Str::uuid()->toString(),
        'paid_at' => now(),
    ]);

    // 4. Probar el Resource directamente (más robusto que depender de una ruta HTTP específica)
    $resource = CashSessionResource::make($session);
    $data = $resource->toArray(request());
    
    // 5. Validar que el desglose existe y tiene los valores correctos
    expect($data)->toHaveKey('breakdown');
    expect($data['breakdown']['cash_sales'])->toBe(11900);
    expect($data['breakdown']['card_sales'])->toBe(23800);
    expect($data['breakdown']['total_tips'])->toBe(3000); // 1000 + 2000
    expect($data['breakdown']['cash_tips_received'])->toBe(1000);
});
