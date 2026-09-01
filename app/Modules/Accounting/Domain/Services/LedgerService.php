<?php

namespace Modules\Accounting\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Domain\Entities\Account;
use Modules\Accounting\Domain\Entities\JournalEntry;
use Modules\Accounting\Domain\Entities\LedgerEntry;
use Modules\Accounting\Domain\Exceptions\UnbalancedJournalEntryException;
use Modules\Accounting\Domain\ValueObjects\ReferenceType;

/**
 * Servicio de contabilidad de doble entrada.
 * 
 * Garantiza la integridad contable:
 * - Cada asiento está balanceado (SUM(debits) == SUM(credits))
 * - Cada línea tiene solo débito o solo crédito
 * - Todas las operaciones son transaccionales
 */
class LedgerService
{
    /**
     * Crea un asiento contable balanceado.
     * 
     * @param int $companyId
     * @param int $branchId
     * @param ReferenceType $referenceType
     * @param int $referenceId
     * @param array $lines Array de líneas: [['account_id' => 1, 'debit' => 100, 'credit' => 0, 'description' => ''], ...]
     * @param string|null $description
     * @param int|null $userId
     * @return JournalEntry
     * @throws UnbalancedJournalEntryException
     */
    public function createJournalEntry(
        int $companyId,
        int $branchId,
        ReferenceType $referenceType,
        int $referenceId,
        array $lines,
        ?string $description = null,
        ?int $userId = null
    ): JournalEntry {
        // Validar que hay al menos una línea
        if (empty($lines)) {
            throw UnbalancedJournalEntryException::emptyEntry();
        }

        // Validar cada línea y calcular totales
        $totalDebits = 0;
        $totalCredits = 0;

        foreach ($lines as $line) {
            $debit = $line['debit'] ?? 0;
            $credit = $line['credit'] ?? 0;

            // Cada línea debe tener solo débito O solo crédito
            if ($debit > 0 && $credit > 0) {
                throw UnbalancedJournalEntryException::invalidLine();
            }

            if ($debit == 0 && $credit == 0) {
                throw UnbalancedJournalEntryException::invalidLine();
            }

            $totalDebits += $debit;
            $totalCredits += $credit;
        }

        // Validar balance
        if (abs($totalDebits - $totalCredits) > 0.01) {
            throw UnbalancedJournalEntryException::create($totalDebits, $totalCredits);
        }

        // Crear asiento en transacción
        return DB::transaction(function () use ($companyId, $branchId, $referenceType, $referenceId, $lines, $description, $userId) {
            // Generar número de asiento
            $journalEntryNumber = $this->generateJournalEntryNumber($companyId);

            // Crear JournalEntry
            $journalEntry = JournalEntry::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'journal_entry_number' => $journalEntryNumber,
                'entry_date' => now(),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'user_id' => $userId,
            ]);

            // Crear LedgerEntries
            foreach ($lines as $line) {
                LedgerEntry::create([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $line['account_id'],
                    'debit_amount' => $line['debit'] ?? 0,
                    'credit_amount' => $line['credit'] ?? 0,
                    'description' => $line['description'] ?? null,
                ]);
            }

            return $journalEntry->load('ledgerEntries.account');
        });
    }

    /**
     * Genera un número de asiento único.
     */
    private function generateJournalEntryNumber(int $companyId): string
    {
        $date = now()->format('Ymd');
        $lastEntry = JournalEntry::where('company_id', $companyId)
            ->whereDate('entry_date', today())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastEntry ? (intval(substr($lastEntry->journal_entry_number, -4)) + 1) : 1;

        return sprintf('JE-%s-%04d', $date, $sequence);
    }

    /**
     * Obtiene el balance de una cuenta.
     */
    public function getAccountBalance(int $accountId, ?string $fromDate = null, ?string $toDate = null): float
    {
        $account = Account::findOrFail($accountId);

        $query = $account->ledgerEntries();

        if ($fromDate) {
            $query->whereHas('journalEntry', fn($q) => $q->where('entry_date', '>=', $fromDate));
        }

        if ($toDate) {
            $query->whereHas('journalEntry', fn($q) => $q->where('entry_date', '<=', $toDate));
        }

        $debits = $query->sum('debit_amount');
        $credits = $query->sum('credit_amount');

        return $account->type->normalBalance() === 'debit'
            ? $debits - $credits
            : $credits - $debits;
    }

    /**
     * Obtiene todos los asientos de una referencia específica.
     */
    public function getEntriesByReference(ReferenceType $referenceType, int $referenceId): array
    {
        return JournalEntry::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->with('ledgerEntries.account')
            ->get()
            ->toArray();
    }
}
