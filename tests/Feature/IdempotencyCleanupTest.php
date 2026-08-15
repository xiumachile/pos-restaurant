<?php

use App\Shared\Domain\Entities\IdempotencyKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('comando elimina keys expiradas', function () {
    // Crear 2 keys expiradas y 1 válida
    for ($i = 0; $i < 2; $i++) {
        IdempotencyKey::create([
            'key' => Str::uuid(),
            'request_hash' => hash('sha256', "expired-$i"),
            'response_body' => ['data' => 'old'],
            'response_code' => 200,
            'expires_at' => now()->subDays(2),
        ]);
    }

    IdempotencyKey::create([
        'key' => Str::uuid(),
        'request_hash' => hash('sha256', 'valid'),
        'response_body' => ['data' => 'new'],
        'response_code' => 200,
        'expires_at' => now()->addHour(),
    ]);

    expect(IdempotencyKey::count())->toBe(3);

    // Ejecutar comando
    $this->artisan('idempotency:cleanup-expired', ['--days' => 1])
        ->expectsOutputToContain('2 keys expiradas eliminadas')
        ->assertSuccessful();

    expect(IdempotencyKey::count())->toBe(1);
});

test('comando con dry-run no elimina nada', function () {
    IdempotencyKey::create([
        'key' => Str::uuid(),
        'request_hash' => hash('sha256', 'expired'),
        'response_body' => ['data' => 'old'],
        'response_code' => 200,
        'expires_at' => now()->subDays(2),
    ]);

    $this->artisan('idempotency:cleanup-expired', ['--days' => 1, '--dry-run' => true])
        ->expectsOutputToContain('Se eliminarían 1 keys expiradas')
        ->assertSuccessful();

    // No se eliminó nada
    expect(IdempotencyKey::count())->toBe(1);
});

test('comando no elimina keys válidas', function () {
    IdempotencyKey::create([
        'key' => Str::uuid(),
        'request_hash' => hash('sha256', 'valid'),
        'response_body' => ['data' => 'new'],
        'response_code' => 200,
        'expires_at' => now()->addHour(),
    ]);

    $this->artisan('idempotency:cleanup-expired', ['--days' => 1])
        ->expectsOutputToContain('No hay keys expiradas')
        ->assertSuccessful();

    expect(IdempotencyKey::count())->toBe(1);
});
