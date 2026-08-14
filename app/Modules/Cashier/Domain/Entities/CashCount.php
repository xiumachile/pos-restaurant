<?php

namespace Modules\Cashier\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Cashier\Domain\ValueObjects\CashCountType;
use Modules\Cashier\Domain\ValueObjects\Denomination;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Payments\Domain\Entities\CashSession;

/**
 * Arqueo de caja (conteo físico de efectivo).
 * Registra el conteo detallado por denominación y calcula discrepancias.
 */
class CashCount extends Model
{
    use HasUuid;
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'cash_session_id',
        'user_id',
        'type',
        'reason',
        'expected_amount',
        'counted_amount',
        'difference',
        'denominations',
        'cash_amount',
        'card_amount',
        'transfer_amount',
        'other_amount',
        'notes',
        'has_discrepancy',
        'discrepancy_explanation',
        'supervised_by',
        'supervised_at',
    ];

    protected $casts = [
        'type' => CashCountType::class,
        'expected_amount' => 'decimal:2',
        'counted_amount' => 'decimal:2',
        'difference' => 'decimal:2',
        'denominations' => 'array',
        'cash_amount' => 'decimal:2',
        'card_amount' => 'decimal:2',
        'transfer_amount' => 'decimal:2',
        'other_amount' => 'decimal:2',
        'has_discrepancy' => 'boolean',
        'supervised_at' => 'datetime',
    ];

    protected $attributes = [
        'cash_amount' => 0,
        'card_amount' => 0,
        'transfer_amount' => 0,
        'other_amount' => 0,
        'has_discrepancy' => false,
    ];

    // Umbral para considerar discrepancia significativa (CLP)
    private const DISCREPANCY_THRESHOLD = 100.0;

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervised_by');
    }

    /**
     * Calcula el monto contado a partir del desglose de denominaciones.
     */
    public function recalculateFromDenominations(): float
    {
        $denominations = $this->denominations ?? [];
        
        // Calcular por separado para evitar que array_merge reindexe keys numéricos
        $billsTotal = Denomination::calculateTotal($denominations['bills'] ?? []);
        $coinsTotal = Denomination::calculateTotal($denominations['coins'] ?? []);
        $total = $billsTotal + $coinsTotal;
        
        $this->counted_amount = $total;
        $this->difference = round($total - (float) $this->expected_amount, 2);
        $this->has_discrepancy = abs($this->difference) > self::DISCREPANCY_THRESHOLD;
        
        return $total;
    }

    /**
     * Verifica si la discrepancia es positiva (sobrante).
     */
    public function hasSurplus(): bool
    {
        return (float) $this->difference > self::DISCREPANCY_THRESHOLD;
    }

    /**
     * Verifica si la discrepancia es negativa (faltante).
     */
    public function hasShortage(): bool
    {
        return (float) $this->difference < -self::DISCREPANCY_THRESHOLD;
    }

    /**
     * Verifica si el arqueo está cuadrado (sin discrepancia significativa).
     */
    public function isBalanced(): bool
    {
        return !$this->has_discrepancy;
    }

    /**
     * Porcentaje de discrepancia respecto al esperado.
     */
    public function discrepancyPercentage(): float
    {
        $expected = (float) $this->expected_amount;
        if ($expected === 0.0) {
            return 0.0;
        }
        return round((abs((float) $this->difference) / $expected) * 100, 2);
    }

    /**
     * Supervisa el arqueo (requerido para discrepancias grandes).
     */
    public function supervise(User $supervisor, string $explanation): void
    {
        $this->supervised_by = $supervisor->id;
        $this->supervised_at = now();
        $this->discrepancy_explanation = $explanation;
        $this->save();
    }

    public function scopeOfType($query, CashCountType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeWithDiscrepancy($query)
    {
        return $query->where('has_discrepancy', true);
    }
}
