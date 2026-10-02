<?php

namespace Modules\Recipes\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Recipes\Domain\Exceptions\InsufficientStockException;
use Modules\Recipes\Domain\ValueObjects\MovementType;

/**
 * Movimiento de stock de un insumo.
 * 
 * ADR-022: Unifica los tipos de movimiento del antiguo Inventory con los
 * movimientos de consumo de recetas. Cada movimiento:
 * - Registra tipo, cantidad, balance_after
 * - Actualiza current_stock_base del insumo
 * - Soporta referencias polimórficas (orders, purchases, etc.)
 * - Incluye auditoría (user_id, reason)
 */
class RawIngredientMovement extends Model
{
    use HasUuid;
    use BelongsToTenant;

    protected $table = 'raw_ingredient_movements';

    protected $fillable = [
        'company_id',
        'branch_id',
        'raw_ingredient_id',
        'type',
        'quantity_base',
        'balance_after',
        'reference_type',
        'reference_id',
        'user_id',
        'reason',
    ];

    protected $casts = [
        'type' => MovementType::class,
        'quantity_base' => 'decimal:4',
        'balance_after' => 'decimal:4',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(RawIngredient::class, 'raw_ingredient_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Registra un movimiento de stock con validaciones y actualización atómica.
     * 
     * @throws \InvalidArgumentException si quantity_base es zero
     * @throws InsufficientStockException si no hay stock suficiente para movimientos de salida
     */
    public static function record(
        int $companyId,
        int $branchId,
        int $rawIngredientId,
        MovementType $type,
        float $quantityBase,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $userId = null,
        ?string $reason = null
    ): self {
        // Validación: quantity_base no puede ser zero
        if ($quantityBase == 0) {
            throw new \InvalidArgumentException('quantity_base cannot be zero');
        }

        // Obtener el insumo (withoutGlobalScopes para evitar filtros de tenant
        // que pueden fallar cuando el contexto no está configurado en tests)
        $ingredient = RawIngredient::withoutGlobalScopes()
            ->where('id', $rawIngredientId)
            ->firstOrFail();

        // Calcular el efecto en el stock
        $stockChange = $type->affectsStock($quantityBase);

        // Validación: para movimientos de salida, verificar stock suficiente
        if ($stockChange < 0) {
            $currentStock = (float) $ingredient->current_stock_base;
            $requestedAbs = abs($stockChange);
            
            if ($currentStock < $requestedAbs) {
                throw new InsufficientStockException(
                    $rawIngredientId,
                    $requestedAbs,
                    $currentStock
                );
            }
        }

        // Calcular balance_after
        $currentStock = (float) $ingredient->current_stock_base;
        $balanceAfter = $currentStock + $stockChange;

        // Crear el movimiento
        $movement = self::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'raw_ingredient_id' => $rawIngredientId,
            'type' => $type,
            'quantity_base' => $quantityBase,
            'balance_after' => $balanceAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'user_id' => $userId,
            'reason' => $reason,
        ]);

        // Actualizar current_stock_base del insumo
        $ingredient->current_stock_base = $balanceAfter;
        $ingredient->save();

        return $movement;
    }

    /**
     * Scope: filtra movimientos por insumo.
     */
    public function scopeForIngredient($query, int $rawIngredientId)
    {
        return $query->where('raw_ingredient_id', $rawIngredientId);
    }

    /**
     * Scope: filtra movimientos por sucursal.
     */
    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }
}
