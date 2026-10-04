<?php

namespace Modules\FloorPlan\Domain\Entities;

use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Objeto gráfico dentro del plano (Sección 8.2 de la spec)
 * 
 * IMPORTANTE: Los objetos gráficos NO son entidades operativas.
 * La mesa física pertenece al módulo Tables; el objeto gráfico
 * solo determina dónde y cómo se representa.
 */
class FloorPlanObject extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'floor_plan_id',
        'object_type',
        'object_key',
        'x',
        'y',
        'width',
        'height',
        'rotation',
        'z_index',
        'properties',
    ];

    protected $casts = [
        'x' => 'integer',
        'y' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'rotation' => 'integer',
        'z_index' => 'integer',
        'properties' => 'array',
    ];

    // Valores por defecto para atributos con defaults en BD
    protected $attributes = [
        'x' => 0,
        'y' => 0,
        'rotation' => 0,
        'z_index' => 0,
    ];

    // Tipos de objeto (Sección 3 de la spec)
    public const TYPE_TABLE = 'table';
    public const TYPE_CHAIR = 'chair';
    public const TYPE_PLANT = 'plant';
    public const TYPE_WALL = 'wall';
    public const TYPE_DOOR = 'door';
    public const TYPE_WINDOW = 'window';
    public const TYPE_COLUMN = 'column';
    public const TYPE_BAR = 'bar';
    public const TYPE_DECORATION = 'decoration';
    public const TYPE_FURNITURE = 'furniture';
    public const TYPE_SEPARATOR = 'separator';
    public const TYPE_SERVICE = 'service';
    public const TYPE_INFRASTRUCTURE = 'infrastructure';
    public const TYPE_CUSTOM = 'custom';

    public function floorPlan(): BelongsTo
    {
        return $this->belongsTo(FloorPlan::class);
    }

    /**
     * Para objetos de tipo 'table', obtiene la mesa operativa vinculada.
     */
    public function linkedTable(): ?\Modules\Tables\Domain\Entities\RestaurantTable
    {
        if ($this->object_type !== self::TYPE_TABLE || !$this->object_key) {
            return null;
        }

        return \Modules\Tables\Domain\Entities\RestaurantTable::where('uuid', $this->object_key)->first();
    }

    // Scopes
    public function scopeOfType($query, string $type)
    {
        return $query->where('object_type', $type);
    }

    public function scopeOrderedByLayer($query)
    {
        return $query->orderBy('z_index')->orderBy('id');
    }
}
