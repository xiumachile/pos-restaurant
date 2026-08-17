<?php

namespace Modules\Cashier\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Cashier\Domain\ValueObjects\CardTipHandling;
use Modules\Cashier\Domain\ValueObjects\TipPolicyType;

class TipPolicy extends Model
{
    use HasUuid;
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'policy_type',
        'card_tip_handling',
        'pool_split_method',
        'waiter_percentage',
        'pool_percentage',
        'is_active',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'policy_type' => TipPolicyType::class,
        'card_tip_handling' => CardTipHandling::class,
        'waiter_percentage' => 'decimal:2',
        'pool_percentage' => 'decimal:2',
        'is_active' => 'boolean',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
    ];

    /**
     * Scope: políticas activas actualmente.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now());
            });
    }

    /**
     * Resuelve la política efectiva para una branch.
     * Prioridad: branch específica → company → default.
     */
    public static function resolveForBranch(int $companyId, ?int $branchId): self
    {
        // 1. Política específica de la branch
        if ($branchId) {
            $branchPolicy = self::where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->active()
                ->orderByDesc('effective_from')
                ->first();

            if ($branchPolicy) {
                return $branchPolicy;
            }
        }

        // 2. Política de la company
        $companyPolicy = self::where('company_id', $companyId)
            ->whereNull('branch_id')
            ->active()
            ->orderByDesc('effective_from')
            ->first();

        if ($companyPolicy) {
            return $companyPolicy;
        }

        // 3. Default: waiter_keeps
        return new self([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'policy_type' => TipPolicyType::WAITER_KEEPS,
            'card_tip_handling' => CardTipHandling::CASH_PAYOUT,
            'pool_split_method' => 'equal',
            'waiter_percentage' => 100,
            'pool_percentage' => 0,
            'is_active' => true,
        ]);
    }

    /**
     * Verifica si las propinas en efectivo deben salir de caja.
     */
    public function cashTipsLeaveRegister(): bool
    {
        // Las propinas en efectivo SIEMPRE salen de caja (son del garzón)
        return true;
    }

    /**
     * Verifica si las propinas con tarjeta salen de caja.
     */
    public function cardTipsLeaveRegister(): bool
    {
        return $this->card_tip_handling === CardTipHandling::CASH_PAYOUT;
    }
}
