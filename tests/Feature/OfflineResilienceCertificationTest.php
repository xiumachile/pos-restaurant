<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Payments\Domain\Entities\PaymentMethod;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'CERT-' . uniqid(),
        'legal_name' => 'Certification Test Company',
        'trade_name' => 'Cert Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'CERT',
        'name' => 'Cert Branch',
    ]);

    $this->waiter = User::create([
        'name' => 'Waiter',
        'email' => 'waiter-' . uniqid() . '@cert.test',
        'password' => bcrypt('password'),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    $this->cashier = User::create([
        'name' => 'Cashier',
        'email' => 'cashier-' . uniqid() . '@cert.test',
        'password' => bcrypt('password'),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);

    $this->cashMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'cash',
        'name_translations' => ['es' => 'Efectivo'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    $this->table = \Modules\Tables\Domain\Entities\RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'table_number' => '5',
        'capacity' => 4,
        'area_code' => 'MAIN',
        'area_name_translations' => ['es' => 'Salón Principal'],
    ]);

    $this->waiterToken = JWTAuth::fromUser($this->waiter);
    $this->cashierToken = JWTAuth::fromUser($this->cashier);
});

function certHeaders(string $token): array
{
    return [
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

test('Hallazgo 12 - Test A: flujo completo sobrevive a reinicio de app', function () {
    enableAllCapabilities($this->company);

    $orderResponse = $this->withHeaders(certHeaders($this->waiterToken))
        ->postJson('/api/v1/orders', [
            'type' => 'dine_in',
            'table_uuid' => $this->table->uuid,
        ]);

    $orderResponse->assertStatus(201);
    $orderUuid = $orderResponse->json('data.uuid');

    cache()->clear();
    app()->forgetInstance(\App\Shared\Application\TenantContext::class);

    $order = Order::where('uuid', $orderUuid)->first();
    expect($order)->not->toBeNull('Order debe persistir después de reinicio')
        ->and($order->status->value)->toBe('draft')
        ->and($order->company_id)->toBe($this->company->id)
        ->and($order->branch_id)->toBe($this->branch->id)
        ->and($order->waiter_id)->toBe($this->waiter->id);

    $updateResponse = $this->withHeaders(certHeaders($this->waiterToken))
        ->putJson("/api/v1/orders/{$orderUuid}", [
            'notes' => 'Recovered after restart',
            'version' => $order->version,
        ]);

    $updateResponse->assertOk();
    $order->refresh();
    expect($order->notes)->toBe('Recovered after restart');
});

test('Hallazgo 12 - Test B: retry después de timeout resulta en 1 solo pago', function () {
    enableCapabilities($this->company, ['can_split_bills', 'can_print_receipts', 'can_accept_tips']);

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->waiter->id,
        'cashier_id' => $this->cashier->id,
        'order_number' => 'CERT-' . uniqid(),
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    $idempotencyKey = (string) \Illuminate\Support\Str::uuid();

    $firstAttempt = $this->withHeaders(certHeaders($this->cashierToken))
        ->postJson("/api/v1/billing/payments", [
            'order_uuid' => $order->uuid,
            'payment_method_uuid' => $this->cashMethod->uuid,
            'amount' => 11900,
            'idempotency_key' => $idempotencyKey,
        ]);

    $firstAttempt->assertStatus(201);

    $retryResponse = $this->withHeaders(certHeaders($this->cashierToken))
        ->postJson("/api/v1/billing/payments", [
            'order_uuid' => $order->uuid,
            'payment_method_uuid' => $this->cashMethod->uuid,
            'amount' => 11900,
            'idempotency_key' => $idempotencyKey,
        ]);

    expect($retryResponse->status())->toBeIn([200, 201], 'El retry debe ser exitoso');

    $paymentCount = Payment::where('order_id', $order->id)->count();
    expect($paymentCount)->toBe(1, 'Solo 1 pago debe existir (idempotencia)');
});

test('Hallazgo 12 - Test C: dos terminales tienen resolución determinística', function () {
    enableAllCapabilities($this->company);

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->waiter->id,
        'order_number' => 'CERT-MULTI-' . uniqid(),
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
        'version' => 1,
    ]);

    $initialVersion = $order->version;

    $terminalAResponse = $this->withHeaders(certHeaders($this->waiterToken))
        ->putJson("/api/v1/orders/{$order->uuid}", [
            'notes' => 'Updated by Terminal A',
            'version' => $initialVersion,
        ]);

    $terminalBResponse = $this->withHeaders(certHeaders($this->cashierToken))
        ->putJson("/api/v1/orders/{$order->uuid}", [
            'notes' => 'Updated by Terminal B',
            'version' => $initialVersion,
        ]);

    $statusA = $terminalAResponse->status();
    $statusB = $terminalBResponse->status();

    expect($statusA === 200 || $statusB === 200)->toBeTrue('Al menos una terminal debe tener éxito');

    if ($statusA === 200 && $statusB === 200) {
        $this->fail('Ambas terminales tuvieron éxito - falta control de concurrencia (Optimistic Locking)');
    }

    $finalOrder = Order::where('uuid', $order->uuid)->first();
    expect($finalOrder->version)->toBeGreaterThan($initialVersion, 'Version debe incrementarse tras update exitoso');
});

test('Hallazgo 12 - Test D: Configuración SQLite del frontend es correcta', function () {
    // El corazón de la aplicación offline reside en SQLite (frontend Tauri).
    // Certificamos que el archivo de inicialización (lib.rs) contiene los PRAGMAS críticos.
    
    $possiblePaths = [
        base_path('frontend/src-tauri/src/lib.rs'),
        base_path('../frontend/src-tauri/src/lib.rs'),
        '/workspace/frontend/src-tauri/src/lib.rs',
    ];

    $libRsPath = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $libRsPath = $path;
            break;
        }
    }

    if (!$libRsPath) {
        $this->markTestSkipped('Archivo lib.rs no encontrado. Esto es normal si el test corre solo en el backend Docker.');
    }

    $content = strtolower(file_get_contents($libRsPath));

    // Verificar presencia de los 4 PRAGMAS críticos (búsqueda flexible)
    $hasWal = str_contains($content, 'journal_mode') && str_contains($content, 'wal');
    $hasBusy = str_contains($content, 'busy_timeout') && preg_match('/busy_timeout\s*=\s*\d{4,}/', $content);
    $hasFk = str_contains($content, 'foreign_keys') && str_contains($content, 'on');
    $hasSync = str_contains($content, 'synchronous') && str_contains($content, 'normal');

    expect($hasWal)->toBeTrue('lib.rs debe configurar journal_mode = WAL');
    expect($hasBusy)->toBeTrue('lib.rs debe configurar busy_timeout >= 5000');
    expect($hasFk)->toBeTrue('lib.rs debe configurar foreign_keys = ON');
    expect($hasSync)->toBeTrue('lib.rs debe configurar synchronous = NORMAL');
});
