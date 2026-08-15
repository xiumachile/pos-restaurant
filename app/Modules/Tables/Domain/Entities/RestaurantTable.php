<?php

namespace Modules\Tables\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasTranslations;
use App\Shared\Domain\Traits\HasUuid;
use App\Shared\Domain\Traits\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Tables\Domain\Exceptions\InvalidTableStatusTransition;
use Modules\Tables\Domain\Services\TableStateMachine;
use Modules\Tables\Domain\ValueObjects\TableStatus;

class RestaurantTable extends Model
{
    use HasUuid;
    use Syncable;
    use BelongsToTenant;
    use HasTranslations;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'area_code',
        'area_name_translations',
        'table_number',
        'capacity',
        'status',
        'current_order_id',
    ];

    protected $casts = [
        'area_name_translations' => 'array',
        'capacity' => 'integer',
        'status' => TableStatus::class,
    ];

    protected array $translatableFields = ['area_name_translations'];

    /**
     * Máquina de estados (inyectada en métodos de transición).
     */
    protected TableStateMachine $stateMachine;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->stateMachine = new TableStateMachine();
    }

    // ============================================
    // RELACIONES
    // ============================================

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Pedido actual (se activará en Fase 5 cuando exista el modelo Order).
     *
     * public function currentOrder(): BelongsTo
     * {
     *     return $this->belongsTo(Order::class, 'current_order_id');
     * }
     */

    // ============================================
    // SCOPES
    // ============================================

    public function scopeAvailable($query)
    {
        return $query->where('status', TableStatus::Available);
    }

    public function scopeOccupied($query)
    {
        return $query->where('status', TableStatus::Occupied);
    }

    public function scopeInArea($query, string $areaCode)
    {
        return $query->where('area_code', $areaCode);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('area_code')->orderBy('table_number');
    }

    // ============================================
    // MÁQUINA DE ESTADOS
    // ============================================

    /**
     * Ocupa la mesa asociándola a un pedido.
     * Transición: available → occupied
     */
    public function occupy(int $orderId): self
    {
        $this->stateMachine->assertTransition($this->status, TableStatus::Occupied);

        if ($orderId <= 0) {
            throw InvalidTableStatusTransition::occupyWithoutOrder();
        }

        $this->status = TableStatus::Occupied;
        $this->current_order_id = $orderId;

        return $this;
    }

    /**
     * Solicita la cuenta (pasa a facturación).
     * Transición: occupied → billing
     */
    public function requestBilling(): self
    {
        $this->stateMachine->assertTransition($this->status, TableStatus::Billing);

        $this->status = TableStatus::Billing;

        return $this;
    }

    /**
     * Libera la mesa después del pago.
     * Transición: billing → available
     */
    public function free(): self
    {
        $this->stateMachine->assertTransition($this->status, TableStatus::Available);

        $this->status = TableStatus::Available;
        $this->current_order_id = null;

        return $this;
    }

    /**
     * Envía la mesa a mantenimiento.
     * Transición: available → maintenance
     */
    public function setMaintenance(): self
    {
        $this->stateMachine->assertTransition($this->status, TableStatus::Maintenance);

        $this->status = TableStatus::Maintenance;

        return $this;
    }

    /**
     * Habilita la mesa desde mantenimiento.
     * Transición: maintenance → available
     */
    public function enable(): self
    {
        $this->stateMachine->assertTransition($this->status, TableStatus::Available);

        $this->status = TableStatus::Available;

        return $this;
    }

    // ============================================
    // HELPERS
    // ============================================

    /**
     * Verifica si la mesa tiene un pedido activo asociado.
     */
    public function hasActiveOrder(): bool
    {
        return $this->current_order_id !== null;
    }

    /**
     * Verifica si la mesa puede recibir nuevos pedidos.
     */
    public function isAvailable(): bool
    {
        return $this->status === TableStatus::Available;
    }

    /**
     * Obtiene el nombre del área traducido.
     */
    public function getAreaNameAttribute(): string
    {
        return $this->translate('area_name_translations', null, 'Sin área');
    }
}
