<?php

namespace Modules\Recipes\Domain\Entities;

use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\Domain\Entities\User;

/**
 * Registro inmutable de compra de materia prima con conversión automática.
 */
class RawIngredientPurchase extends Model
{
    use HasUuid;

    protected $table = 'raw_ingredient_purchases';

    protected $fillable = [
        'raw_ingredient_id',
        'user_id',
        'purchase_unit_name',
        'purchase_quantity',
        'conversion_factor_to_base',
        'total_base_quantity_added',
        'total_purchase_cost',
        'calculated_cost_per_base_unit',
        'purchase_date',
    ];

    protected $casts = [
        'purchase_quantity' => 'decimal:2',
        'conversion_factor_to_base' => 'decimal:4',
        'total_base_quantity_added' => 'decimal:4',
        'total_purchase_cost' => 'decimal:2',
        'calculated_cost_per_base_unit' => 'decimal:6',
        'purchase_date' => 'datetime',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(RawIngredient::class, 'raw_ingredient_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
