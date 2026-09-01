<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Domain\Entities\Account;
use Modules\Accounting\Domain\Entities\JournalEntry;
use Modules\Accounting\Domain\Exceptions\UnbalancedJournalEntryException;
use Modules\Accounting\Domain\Services\LedgerService;
use Modules\Accounting\Domain\ValueObjects\AccountType;
use Modules\Accounting\Domain\ValueObjects\ReferenceType;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'LEDGER-' . uniqid(),
        'legal_name' => 'Ledger Test Company',
        'trade_name' => 'Ledger Test',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'LDG',
        'name' => 'Ledger Branch',
    ]);

    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'ledger-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'admin',
    ]);

    // Crear cuentas de prueba
    $this->cashAccount = Account::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => '1100',
        'name' => 'Efectivo',
        'type' => AccountType::ASSET,
    ]);

    $this->revenueAccount = Account::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => '4100',
        'name' => 'Ingresos',
        'type' => AccountType::REVENUE,
    ]);

    $this->taxAccount = Account::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => '2100',
        'name' => 'IVA por Pagar',
        'type' => AccountType::LIABILITY,
    ]);

    $this->ledgerService = app(LedgerService::class);
});

test('crea asiento contable balanceado', function () {
    $lines = [
        ['account_id' => $this->cashAccount->id, 'debit' => 11900, 'credit' => 0, 'description' => 'Pago recibido'],
        ['account_id' => $this->revenueAccount->id, 'debit' => 0, 'credit' => 10000, 'description' => 'Ingreso por venta'],
        ['account_id' => $this->taxAccount->id, 'debit' => 0, 'credit' => 1900, 'description' => 'IVA 19%'],
    ];

    $entry = $this->ledgerService->createJournalEntry(
        $this->company->id,
        $this->branch->id,
        ReferenceType::PAYMENT,
        1,
        $lines,
        'Pago de pedido #1',
        $this->user->id
    );

    expect($entry)->toBeInstanceOf(JournalEntry::class)
        ->and($entry->isBalanced())->toBeTrue()
        ->and($entry->totalDebits())->toBe(11900.0)
        ->and($entry->totalCredits())->toBe(11900.0)
        ->and($entry->ledgerEntries)->toHaveCount(3);
});

test('rechaza asiento desbalanceado', function () {
    $lines = [
        ['account_id' => $this->cashAccount->id, 'debit' => 10000, 'credit' => 0],
        ['account_id' => $this->revenueAccount->id, 'debit' => 0, 'credit' => 9000], // Falta 1000
    ];

    $this->ledgerService->createJournalEntry(
        $this->company->id,
        $this->branch->id,
        ReferenceType::PAYMENT,
        1,
        $lines
    );
})->throws(UnbalancedJournalEntryException::class);

test('rechaza línea con débito y crédito simultáneamente', function () {
    $lines = [
        ['account_id' => $this->cashAccount->id, 'debit' => 10000, 'credit' => 5000], // Inválido
    ];

    $this->ledgerService->createJournalEntry(
        $this->company->id,
        $this->branch->id,
        ReferenceType::PAYMENT,
        1,
        $lines
    );
})->throws(UnbalancedJournalEntryException::class);

test('rechaza asiento vacío', function () {
    $this->ledgerService->createJournalEntry(
        $this->company->id,
        $this->branch->id,
        ReferenceType::PAYMENT,
        1,
        []
    );
})->throws(UnbalancedJournalEntryException::class);

test('calcula balance de cuenta correctamente', function () {
    // Crear asiento: +10000 en efectivo
    $this->ledgerService->createJournalEntry(
        $this->company->id,
        $this->branch->id,
        ReferenceType::PAYMENT,
        1,
        [
            ['account_id' => $this->cashAccount->id, 'debit' => 10000, 'credit' => 0],
            ['account_id' => $this->revenueAccount->id, 'debit' => 0, 'credit' => 10000],
        ]
    );

    // Crear otro asiento: -3000 en efectivo (retiro)
    $this->ledgerService->createJournalEntry(
        $this->company->id,
        $this->branch->id,
        ReferenceType::PAYOUT,
        1,
        [
            ['account_id' => $this->cashAccount->id, 'debit' => 0, 'credit' => 3000],
            ['account_id' => $this->revenueAccount->id, 'debit' => 3000, 'credit' => 0],
        ]
    );

    // Balance de efectivo: 10000 - 3000 = 7000
    $balance = $this->ledgerService->getAccountBalance($this->cashAccount->id);
    expect($balance)->toBe(7000.0);

    // Balance de ingresos: 10000 - 3000 = 7000 (crédito neto)
    $revenueBalance = $this->ledgerService->getAccountBalance($this->revenueAccount->id);
    expect($revenueBalance)->toBe(7000.0);
});

test('obtiene asientos por referencia', function () {
    $this->ledgerService->createJournalEntry(
        $this->company->id,
        $this->branch->id,
        ReferenceType::PAYMENT,
        123,
        [
            ['account_id' => $this->cashAccount->id, 'debit' => 5000, 'credit' => 0],
            ['account_id' => $this->revenueAccount->id, 'debit' => 0, 'credit' => 5000],
        ]
    );

    $entries = $this->ledgerService->getEntriesByReference(ReferenceType::PAYMENT, 123);

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['ledger_entries'])->toHaveCount(2);
});

test('cuenta asset tiene saldo normal débito', function () {
    expect($this->cashAccount->type->normalBalance())->toBe('debit');
});

test('cuenta liability tiene saldo normal crédito', function () {
    expect($this->taxAccount->type->normalBalance())->toBe('credit');
});

test('cuenta revenue tiene saldo normal crédito', function () {
    expect($this->revenueAccount->type->normalBalance())->toBe('credit');
});
