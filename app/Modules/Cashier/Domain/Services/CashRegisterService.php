<?php

namespace Modules\Cashier\Domain\Services;

use Illuminate\Support\Facades\Log;
use Modules\Cashier\Domain\Entities\CashRegister;
use Modules\Cashier\Domain\Exceptions\CashierException;
use Modules\Branches\Domain\Entities\Branch;

/**
 * Servicio para gestionar cajas registradoras físicas.
 */
class CashRegisterService
{
    /**
     * Crea una nueva caja registradora.
     */
    public function create(
        Branch $branch,
        string $name,
        string $code,
        float $openingAmountDefault = 50000.0,
        float $maxAmount = 500000.0,
        bool $requiresDualControl = false,
        ?string $description = null
    ): CashRegister {
        // Validar que el código sea único en la sucursal
        $existing = CashRegister::where('company_id', $branch->company_id)
            ->where('branch_id', $branch->id)
            ->where('code', $code)
            ->first();

        if ($existing) {
            throw new CashierException(
                "Ya existe una caja con código '{$code}' en esta sucursal."
            );
        }

        if ($openingAmountDefault < 0) {
            throw new CashierException('El monto de apertura por defecto no puede ser negativo.');
        }

        if ($maxAmount <= 0) {
            throw new CashierException('El monto máximo debe ser positivo.');
        }

        $register = CashRegister::create([
            'company_id' => $branch->company_id,
            'branch_id' => $branch->id,
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'opening_amount_default' => $openingAmountDefault,
            'max_amount' => $maxAmount,
            'requires_dual_control' => $requiresDualControl,
        ]);

        Log::info('Caja registradora creada', [
            'register_id' => $register->id,
            'code' => $code,
            'branch_id' => $branch->id,
        ]);

        return $register;
    }

    /**
     * Obtiene las cajas disponibles (sin sesión abierta) de una sucursal.
     */
    public function getAvailableRegisters(int $companyId, int $branchId)
    {
        return CashRegister::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->active()
            ->available()
            ->get();
    }

    /**
     * Obtiene todas las cajas activas de una sucursal.
     */
    public function getActiveRegisters(int $companyId, int $branchId)
    {
        return CashRegister::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->active()
            ->with('sessions')
            ->get();
    }

    /**
     * Activa/desactiva una caja.
     */
    public function toggleActive(CashRegister $register, bool $active): CashRegister
    {
        if (!$active && $register->isBusy()) {
            throw new CashierException(
                'No se puede desactivar una caja con una sesión abierta. Ciérrela primero.'
            );
        }

        $register->is_active = $active;
        $register->save();

        return $register;
    }
}
