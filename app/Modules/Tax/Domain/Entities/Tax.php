<?php

namespace Modules\Tax\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Companies\Domain\Entities\Company;
use Modules\Tax\Domain\ValueObjects\TaxType;

/**
 * Impuesto configurable por empresa.
 * 
 * Soporta IVA estándar, exentos, tasas reducidas e impuestos fijos.
 * Cada empresa puede tener múltiples impuestos configurados.
 */
class Tax extends Model
{
    use HasUuid;
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'type',
        'rate',
        'is_default',
        'is_active',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'type' => TaxType::class,
        'rate' => 'decimal:4',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'is_default' => false,
        'is_active' => true,
        'sort_order' => 0,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Calcula el impuesto para un monto base.
     * 
     * @param float $baseAmount Monto neto (sin impuesto)
     * @param float $quantity Cantidad de unidades
     * @return float Monto del impuesto
     */
    public function calculate(float $baseAmount, float $quantity = 1): float
    {
        if (!$this->is_active) {
            return 0.0;
        }

        return $this->type->calculate($baseAmount, $quantity, (float) $this->rate);
    }

    /**
     * Obtiene la tasa efectiva como porcentaje (para compatibilidad).
     * Para impuestos fijos o exentos, retorna 0.
     */
    public function effectiveRate(): float
    {
        return $this->type === TaxType::PERCENT ? (float) $this->rate : 0.0;
    }

    /**
     * Verifica si este impuesto es exento.
     */
    public function isExempt(): bool
    {
        return $this->type === TaxType::EXEMPT;
    }

    /**
     * Verifica si este impuesto es un porcentaje.
     */
    public function isPercentage(): bool
    {
        return $this->type === TaxType::PERCENT;
    }

    /**
     * Marca este impuesto como default (desmarca otros).
     */
    public function markAsDefault(): void
    {
        // Desmarcar otros defaults de la misma empresa
        self::where('company_id', $this->company_id)
            ->where('id', '!=', $this->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $this->is_default = true;
        $this->save();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
