<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Domain\Entities\Account;
use Modules\Accounting\Domain\Entities\JournalEntry;
use Modules\Accounting\Domain\Services\LedgerService;
use Modules\Accounting\Domain\ValueObjects\AccountType;
use Modules\Accounting\Domain\ValueObjects\ReferenceType;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'LEDGER-IDEMP-' . uniqid(),
        'legal_name' => 'Ledger Idempotency Test',
        'trade_name' => 'Ledger Idemp',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'LI',
        'name' => 'Ledger Idemp Branch',
    ]);

    // Crear cuentas contables
    Account::seedDefaultsFor($this->company->id, $this->branch->id);

    $this->cashAccount = Account::where('company_id', $this->company->id)
        ->where('code', '1100')
        ->first();

    $this->revenueAccount = Account::where('company_id', $this->company->id)
        ->where('code', '4100')
        ->first();

    $this->ledgerService = app(LedgerService::class);
});

test('createJournalEntry es idempotente: misma referencia retorna mismo asiento', function () {
    $lines = [
        ['account_id' => $this->cashAccount->id, 'debit' => 10000, 'credit' => 0],
        ['account_id' => $this->revenueAccount->id, 'debit' => 0, 'credit' => 10000],
    ];

    // Primera llamada: crea el asiento
    $entry1 = $this->ledgerService->createJournalEntry(
        $this->company->id,
        $this->branch->id,
        ReferenceType::PAYMENT,
        999, // reference_id
        $lines,
        'Test idempotencia'
    );

    // Segunda llamada: debe retornar el mismo asiento
    $entry2 = $this->ledgerService->createJournalEntry(
        $this->company->id,
        $this->branch->id,
        ReferenceType::PAYMENT,
        999, // misma reference_id
        $lines,
        'Test idempotencia'
    );

    // Verificar que es el mismo asiento
    expect($entry1->id)->toBe($entry2->id)
        ->and($entry1->uuid)->toBe($entry2->uuid)
        ->and($entry1->journal_entry_number)->toBe($entry2->journal_entry_number);

    // Verificar que solo existe UN asiento en la DB
    $count = JournalEntry::where('reference_type', ReferenceType::PAYMENT)
        ->where('reference_id', 999)
        ->count();
    expect($count)->toBe(1);

    // Verificar que solo existen 2 ledger entries (no 4)
    $ledgerEntriesCount = $entry1->ledgerEntries()->count();
    expect($ledgerEntriesCount)->toBe(2);
});

test('createJournalEntry crea asiento nuevo con diferente referencia', function () {
    $lines = [
        ['account_id' => $this->cashAccount->id, 'debit' => 10000, 'credit' => 0],
        ['account_id' => $this->revenueAccount->id, 'debit' => 0, 'credit' => 10000],
    ];

    // Primera llamada con reference_id = 100
    $entry1 = $this->ledgerService->createJournalEntry(
        $this->company->id,
        $this->branch->id,
        ReferenceType::PAYMENT,
        100,
        $lines,
        'Pago 100'
    );

    // Segunda llamada con reference_id = 200 (diferente)
    $entry2 = $this->ledgerService->createJournalEntry(
        $this->company->id,
        $this->branch->id,
        ReferenceType::PAYMENT,
        200,
        $lines,
        'Pago 200'
    );

    // Verificar que son asientos diferentes
    expect($entry1->id)->not->toBe($entry2->id)
        ->and($entry1->reference_id)->toBe(100)
        ->and($entry2->reference_id)->toBe(200);

    // Verificar que existen DOS asientos en la DB
    $count = JournalEntry::where('reference_type', ReferenceType::PAYMENT)->count();
    expect($count)->toBe(2);
});

test('createJournalEntry con diferente reference_type crea asiento nuevo', function () {
    $lines = [
        ['account_id' => $this->cashAccount->id, 'debit' => 5000, 'credit' => 0],
        ['account_id' => $this->revenueAccount->id, 'debit' => 0, 'credit' => 5000],
    ];

    // Crear asiento de PAYMENT con reference_id = 500
    $paymentEntry = $this->ledgerService->createJournalEntry(
        $this->company->id,
        $this->branch->id,
        ReferenceType::PAYMENT,
        500,
        $lines,
        'Pago 500'
    );

    // Crear asiento de REFUND con MISMO reference_id = 500
    $refundEntry = $this->ledgerService->createJournalEntry(
        $this->company->id,
        $this->branch->id,
        ReferenceType::REFUND,
        500,
        $lines,
        'Reembolso 500'
    );

    // Verificar que son asientos diferentes (diferente reference_type)
    expect($paymentEntry->id)->not->toBe($refundEntry->id)
        ->and($paymentEntry->reference_type)->toBe(ReferenceType::PAYMENT)
        ->and($refundEntry->reference_type)->toBe(ReferenceType::REFUND);

    // Verificar que existen DOS asientos en la DB
    $count = JournalEntry::where('reference_id', 500)->count();
    expect($count)->toBe(2);
});

test('unique constraint en DB previene duplicados a nivel de base de datos', function () {
    $lines = [
        ['account_id' => $this->cashAccount->id, 'debit' => 10000, 'credit' => 0],
        ['account_id' => $this->revenueAccount->id, 'debit' => 0, 'credit' => 10000],
    ];

    // Crear primer asiento
    $this->ledgerService->createJournalEntry(
        $this->company->id,
        $this->branch->id,
        ReferenceType::PAYMENT,
        777,
        $lines
    );

    // Intentar crear segundo asiento manualmente (sin pasar por LedgerService)
    // Esto debería fallar por unique constraint
    $this->expectException(\Illuminate\Database\QueryException::class);
    $this->expectExceptionCode('23505'); // PostgreSQL unique violation

    JournalEntry::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'journal_entry_number' => 'JE-TEST-999',
        'entry_date' => now(),
        'reference_type' => ReferenceType::PAYMENT,
        'reference_id' => 777, // MISMA referencia
        'description' => 'Intento duplicado',
    ]);
});

test('idempotencia preserva ledger entries originales', function () {
    $lines = [
        ['account_id' => $this->cashAccount->id, 'debit' => 15000, 'credit' => 0],
        ['account_id' => $this->revenueAccount->id, 'debit' => 0, 'credit' => 15000],
    ];

    // Primera llamada
    $entry1 = $this->ledgerService->createJournalEntry(
        $this->company->id,
        $this->branch->id,
        ReferenceType::PAYMENT,
        888,
        $lines
    );

    $originalLedgerEntries = $entry1->ledgerEntries()->get()->toArray();

    // Segunda llamada (idempotente)
    $entry2 = $this->ledgerService->createJournalEntry(
        $this->company->id,
        $this->branch->id,
        ReferenceType::PAYMENT,
        888,
        $lines
    );

    // Verificar que las ledger entries son las mismas
    $currentLedgerEntries = $entry2->ledgerEntries()->get()->toArray();

    expect($originalLedgerEntries)->toBe($currentLedgerEntries);
});
