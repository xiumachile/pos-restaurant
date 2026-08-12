<?php

namespace Modules\Inventory\Domain\Entities;

use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Inventory\Domain\ValueObjects\StockMovementType;

class StockMovement extends Model
{
    use HasUuid;

    protected $fillable = [
        'company_id',
        'branch_id',
        'inventory_item_id',
        'type',
        'quantity',
        'balance_after',
        'reference_type',
        'reference_id',
        'user_id',
        'reason',
    ];

    protected $casts = [
        'type' => StockMovementType::class,
        'quantity' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Crea un movimiento de stock con el balance calculado.
     */
    public static function record(
        int $companyId,
        int $branchId,
        int $inventoryItemId,
        StockMovementType $type,
        float $quantity,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $userId = null,
        ?string $reason = null
    ): self {
        // Obtener el stock actual
        $stock = InventoryStock::firstOrCreate(
            [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'inventory_item_id' => $inventoryItemId,
            ],
            ['quantity' => 0]
        );

        // Calcular nuevo balance
        $balanceAfter = $type->applyToStock((float) $stock->quantity, $quantity);

        // Registrar movimiento
        $movement = self::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'inventory_item_id' => $inventoryItemId,
            'type' => $type,
            'quantity' => $quantity,
            'balance_after' => $balanceAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'user_id' => $userId,
            'reason' => $reason,
        ]);

        // Actualizar snapshot de stock
        $stock->quantity = $balanceAfter;
        $stock->last_movement_at = now();
        $stock->save();

        return $movement;
    }
}
