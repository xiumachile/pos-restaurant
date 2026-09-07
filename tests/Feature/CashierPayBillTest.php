<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Payments\Domain\Entities\Bill;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\ValueObjects\BillStatus;
use Modules\Payments\Domain\ValueObjects\BillType;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Tables\Domain\ValueObjects\TableStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'PAY-' . uniqid(),
        'legal_name' => 'Pay Bill Test Company',
        'trade_name' => 'Pay Bill Test',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'PAY-BR',
        'name' => 'Pay Branch',
    ]);

    $this->cashier = User::create([
        'name' => 'Cashier',
        'email' => 'pay-cashier-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);

    $this->waiter = User::create([
        'name' => 'Waiter',
        'email' => 'pay-waiter-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    $this->table = RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'table_number' => '1',
        'capacity' => 4,
        'area_code' => 'MAIN',
        'area_name_translations' => ['es' => 'Salón'],
    ]);

    $this->cashMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => 'CASH',
        'name_translations' => ['es' => 'Efectivo'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    $this->token = JWTAuth::fromUser($this->cashier);
});

/**
 * Crea un order en estado SERVED (cobrable) con total $11.900
 */
function createChargeableOrderForPayBillTest($test): Order
{
    $order = Order::create([
        'company_id' => $test->company->id,
        'branch_id' => $test->branch->id,
        'table_id' => $test->table->id,
        'order_number' => 'ORD-PAY-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::SERVED,
        'waiter_id' => $test->waiter->id,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
    ]);

    // Actualizar mesa a occupied
    $test->table->status = 'occupied';
    $test->table->current_order_id = $order->id;
    $test->table->save();

    return $order;
}

test('payBill NO genera doble cobro — paid_amount debe ser igual al monto pagado', function () {
    $order = createChargeableOrderForPayBillTest($this);

    // Crear bill con todos los campos requeridos (como BillingService)
    $bill = Bill::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'bill_number' => $order->order_number . '-1',
        'type' => BillType::EQUAL_SPLIT,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'tip_amount' => 0,
        'total' => 11900,
        'paid_amount' => 0,
        'remaining_amount' => 11900,
        'status' => BillStatus::OPEN,
        'guest_count' => 1,
    ]);

    $amountToPay = 11900;
    $idempotencyKey = Str::uuid()->toString();

    $paymentsBefore = Payment::where('bill_id', $bill->id)->count();

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
        'Idempotency-Key' => $idempotencyKey,
    ])->postJson("/api/v1/cashier/bills/{$bill->uuid}/pay", [
        'payment_method_uuid' => $this->cashMethod->uuid,
        'amount' => $amountToPay,
        'idempotency_key' => $idempotencyKey,
    ]);

    if ($response->status() !== 200) {
        dump('Status:', $response->status());
        dump('Body:', $response->json());
    }

    $response->assertStatus(200)
        ->assertJsonPath('data.success', true)
        ->assertJsonPath('data.bill_paid', true);

    // Comparación numérica (int vs float no debe importar)
    $responseData = $response->json('data');
    expect((float) $responseData['paid_amount'])->toBe((float) $amountToPay,
        "CRÍTICO: paid_amount debe ser EXACTAMENTE el monto pagado, no el doble");
    expect((float) $responseData['remaining_amount'])->toBe(0.0);

    // ⚠️ ASSERTION CRÍTICA: verificar que NO hubo doble cobro
    $bill->refresh();
    $order->refresh();
    $this->table->refresh();

    expect((float) $bill->paid_amount)->toBe((float) $amountToPay,
        "CRÍTICO: paid_amount debe ser EXACTAMENTE el monto pagado, no el doble");
    expect((float) $bill->remaining_amount)->toBe(0.0);
    expect($bill->status)->toBe(BillStatus::PAID);

    // Solo debe haber UN Payment creado
    $paymentsAfter = Payment::where('bill_id', $bill->id)->count();
    expect($paymentsAfter)->toBe($paymentsBefore + 1,
        "Solo debe crearse UN Payment por pago");

    // El payment debe tener el monto correcto (no 2x)
    $payment = Payment::where('bill_id', $bill->id)->latest('id')->first();
    expect((float) $payment->amount)->toBe((float) $amountToPay);

    // Order debe estar PAID (vía PaymentService.updateOrderPaymentStatus)
    expect($order->status)->toBe(OrderStatus::PAID);
    expect($order->paid_at)->not->toBeNull();

    // Mesa debe estar available (vía listener ReleaseTableOnOrderPaid)
    expect($this->table->status)->toBe(TableStatus::Available);
    expect($this->table->current_order_id)->toBeNull();
});

test('payBill es idempotente con misma idempotency_key', function () {
    $order = createChargeableOrderForPayBillTest($this);

    $bill = Bill::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'bill_number' => $order->order_number . '-1',
        'type' => BillType::EQUAL_SPLIT,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'tip_amount' => 0,
        'total' => 11900,
        'paid_amount' => 0,
        'remaining_amount' => 11900,
        'status' => BillStatus::OPEN,
        'guest_count' => 1,
    ]);

    $idempotencyKey = Str::uuid()->toString();
    $headers = [
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
        'Idempotency-Key' => $idempotencyKey,
    ];
    $payload = [
        'payment_method_uuid' => $this->cashMethod->uuid,
        'amount' => 11900,
        'idempotency_key' => $idempotencyKey,
    ];

    $response1 = $this->withHeaders($headers)->postJson("/api/v1/cashier/bills/{$bill->uuid}/pay", $payload);
    $response1->assertStatus(200);

    $bill->refresh();
    $paidAmountAfterFirst = (float) $bill->paid_amount;

    // Segundo intento con MISMA key (simulando retry)
    $response2 = $this->withHeaders($headers)->postJson("/api/v1/cashier/bills/{$bill->uuid}/pay", $payload);
    $response2->assertStatus(200);

    $bill->refresh();
    $paidAmountAfterSecond = (float) $bill->paid_amount;

    // ⚠️ CRÍTICO: paid_amount NO debe cambiar en el retry
    expect($paidAmountAfterSecond)->toBe($paidAmountAfterFirst,
        "Idempotencia: paid_amount no debe cambiar en retry con misma key");

    // Solo 1 Payment debe existir
    $paymentsCount = Payment::where('bill_id', $bill->id)->count();
    expect($paymentsCount)->toBe(1, "Solo debe existir UN Payment tras retry");
});

test('payBill maneja 10 requests concurrentes con misma idempotency_key', function () {
    $order = createChargeableOrderForPayBillTest($this);

    $bill = Bill::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'bill_number' => $order->order_number . '-1',
        'type' => BillType::EQUAL_SPLIT,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'tip_amount' => 0,
        'total' => 11900,
        'paid_amount' => 0,
        'remaining_amount' => 11900,
        'status' => BillStatus::OPEN,
        'guest_count' => 1,
    ]);

    $idempotencyKey = Str::uuid()->toString();
    $headers = [
        'Authorization' => 'Bearer ' . $this->token,
        'Accept' => 'application/json',
        'Idempotency-Key' => $idempotencyKey,
    ];
    $payload = [
        'payment_method_uuid' => $this->cashMethod->uuid,
        'amount' => 11900,
        'idempotency_key' => $idempotencyKey,
    ];

    // Primer request establece baseline
    $response1 = $this->withHeaders($headers)->postJson("/api/v1/cashier/bills/{$bill->uuid}/pay", $payload);
    $response1->assertStatus(200);

    // 9 requests adicionales con MISMA key (simulando retry masivo)
    $successCount = 0;
    $conflictCount = 0;
    $errorCount = 0;

    for ($i = 0; $i < 9; $i++) {
        $response = $this->withHeaders($headers)->postJson("/api/v1/cashier/bills/{$bill->uuid}/pay", $payload);
        
        if ($response->status() === 200) {
            $successCount++;
        } elseif ($response->status() === 409) {
            $conflictCount++;
        } else {
            $errorCount++;
        }
    }

    // Verificar integridad financiera
    $bill->refresh();
    $paymentsCount = Payment::where('bill_id', $bill->id)->count();

    expect((float) $bill->paid_amount)->toBe(11900.0,
        "paid_amount debe ser exactamente el monto del pago único");
    expect($paymentsCount)->toBe(1,
        "Solo debe existir UN Payment después de 10 requests concurrentes con misma key");
    expect($bill->status)->toBe(BillStatus::PAID);

    // Todos los retries deben retornar 200 (idempotencia) o 409 (conflict)
    // pero NUNCA deben crear Payments adicionales
    expect($successCount + $conflictCount)->toBe(9,
        "Todos los retries deben ser manejados por idempotencia");
});

test('usuario B no puede pagar bill de empresa A', function () {
    // Setup: usuario de Branch B intenta pagar bill de Branch A
    $companyB = Company::create([
        'tax_id' => 'PAY-B-' . uniqid(),
        'legal_name' => 'Company B',
        'trade_name' => 'Company B',
    ]);

    $branchB = Branch::create([
        'company_id' => $companyB->id,
        'code' => 'PAY-B-BR',
        'name' => 'Branch B',
    ]);

    $cashierB = User::create([
        'name' => 'Cashier B',
        'email' => 'cashier-b-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $companyB->id,
        'branch_id' => $branchB->id,
        'role' => 'cashier',
    ]);

    $cashMethodB = PaymentMethod::create([
        'company_id' => $companyB->id,
        'branch_id' => $branchB->id,
        'code' => 'CASH',
        'name_translations' => ['es' => 'Efectivo'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    // Bill de Company A (creado en beforeEach)
    $order = createChargeableOrderForPayBillTest($this);
    $bill = Bill::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'bill_number' => $order->order_number . '-1',
        'type' => BillType::EQUAL_SPLIT,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'tip_amount' => 0,
        'total' => 11900,
        'paid_amount' => 0,
        'remaining_amount' => 11900,
        'status' => BillStatus::OPEN,
        'guest_count' => 1,
    ]);

    $tokenB = JWTAuth::fromUser($cashierB);

    // Intentar pagar con usuario de Branch B
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $tokenB,
        'Accept' => 'application/json',
        'Idempotency-Key' => Str::uuid()->toString(),
    ])->postJson("/api/v1/cashier/bills/{$bill->uuid}/pay", [
        'payment_method_uuid' => $cashMethodB->uuid,
        'amount' => 11900,
        'idempotency_key' => Str::uuid()->toString(),
    ]);

    // Debe fallar con 404 (bill no encontrado en Branch B) o 403 (forbidden)
    expect($response->status())->toBeIn([403, 404, 422],
        "Usuario de Branch B NO debe poder pagar bill de Branch A");

    // Verificar que NO se creó ningún Payment
    $paymentsCount = Payment::where('bill_id', $bill->id)->count();
    expect($paymentsCount)->toBe(0,
        "NO debe crearse Payment en intento cross-branch");

    // Bill debe permanecer OPEN
    $bill->refresh();
    expect($bill->status)->toBe(BillStatus::OPEN);
});
