<?php

namespace Modules\Fiscal\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Fiscal\Domain\ValueObjects\DteType;

/**
 * Rango de folios autorizados por el SII (CAF).
 * Cada tipo de DTE tiene su propio rango de folios independiente.
 */
class DteFolioRange extends Model
{
    use HasUuid;
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'dte_folio_ranges';

    protected $fillable = [
        'company_id',
        'branch_id',
        'dte_type',
        'folio_initial',
        'folio_final',
        'folio_current',
        'caf_xml',
        'authorization_date',
        'authorized_rut',
        'is_active',
        'closed_at',
    ];

    protected $casts = [
        'dte_type' => DteType::class,
        'folio_initial' => 'integer',
        'folio_final' => 'integer',
        'folio_current' => 'integer',
        'authorization_date' => 'date',
        'is_active' => 'boolean',
        'closed_at' => 'datetime',
    ];

    /**
     * Atributos por defecto (evita null cuando no se pasan en create).
     */
    protected $attributes = [
        'is_active' => true,
        'folio_current' => 0,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Folios totales en el rango.
     */
    public function totalFolios(): int
    {
        return ($this->folio_final - $this->folio_initial) + 1;
    }

    /**
     * Folios disponibles restantes.
     */
    public function availableFolios(): int
    {
        if ($this->folio_current < $this->folio_initial) {
            return $this->totalFolios();
        }
        return max(0, $this->folio_final - $this->folio_current);
    }

    /**
     * Porcentaje de uso del rango (0-100).
     */
    public function usagePercentage(): float
    {
        $total = $this->totalFolios();
        if ($total === 0) return 0.0;
        $consumed = ($this->folio_current - $this->folio_initial) + 1;
        return min(100.0, round(($consumed / $total) * 100, 2));
    }

    /**
     * Verifica si hay folios disponibles.
     */
    public function hasAvailableFolios(): bool
    {
        return $this->is_active && $this->availableFolios() > 0;
    }

    /**
     * Verifica si el rango está cerca de agotarse (< 10% disponible).
     */
    public function isRunningLow(): bool
    {
        return $this->hasAvailableFolios() && $this->usagePercentage() >= 90.0;
    }

    /**
     * Verifica si el rango ya se agotó.
     */
    public function isExhausted(): bool
    {
        return !$this->hasAvailableFolios();
    }

    /**
     * Consume un folio del rango (retorna el número del folio consumido).
     * Lanza excepción si no hay folios disponibles.
     */
    public function consumeFolio(): int
    {
        // Recargar con lock para evitar race conditions
        $locked = static::where('id', $this->id)
            ->lockForUpdate()
            ->firstOrFail();

        if (!$locked->hasAvailableFolios()) {
            throw new \Modules\Fiscal\Domain\Exceptions\NoFoliosAvailableException(
                $locked->dte_type,
                $locked->availableFolios()
            );
        }

        // Si es la primera vez que se consume, empezar desde folio_initial
        $nextFolio = $locked->folio_current < $locked->folio_initial 
            ? $locked->folio_initial 
            : $locked->folio_current + 1;

        $locked->folio_current = $nextFolio;
        
        // Si se agotó, marcar como cerrado
        if ($nextFolio >= $locked->folio_final) {
            $locked->closed_at = now();
            $locked->is_active = false;
        }
        
        $locked->save();

        // Sincronizar la instancia actual
        $this->folio_current = $locked->folio_current;
        $this->closed_at = $locked->closed_at;
        $this->is_active = $locked->is_active;

        return $nextFolio;
    }

    /**
     * Scope: rangos activos de un tipo específico.
     */
    public function scopeOfType($query, DteType $type)
    {
        return $query->where('dte_type', $type);
    }

    /**
     * Scope: rangos con folios disponibles.
     */
    public function scopeWithAvailableFolios($query)
    {
        return $query->where('is_active', true)
            ->whereColumn('folio_current', '<', 'folio_final');
    }

    /**
     * Obtiene el rango activo para un tipo de DTE en la sucursal actual.
     */
    public static function getActiveForType(int $companyId, int $branchId, DteType $type): ?self
    {
        return self::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('dte_type', $type)
            ->where('is_active', true)
            ->whereColumn('folio_current', '<', 'folio_final')
            ->orderBy('folio_initial')
            ->first();
    }
}
