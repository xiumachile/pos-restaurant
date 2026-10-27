<?php

namespace Modules\Tables\Domain\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FloorPlanObject extends Model
{
    protected $table = 'floor_plan_objects';
    
    protected $fillable = [
        'company_id',
        'branch_id',
        'uuid',
        'type',             // ✅ Mantenido para compatibilidad con la restricción NOT NULL de la BD
        'zone_uuid',
        'table_uuid',
        'object_type',
        'position_x',
        'position_y',
        'rotation',
        'width',
        'height',
        'z_index',
        'properties',
    ];
    
    protected $casts = [
        'properties' => 'array',
        'position_x' => 'integer',
        'position_y' => 'integer',
        'rotation' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'z_index' => 'integer',
    ];
    
    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }
    
    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_uuid', 'uuid');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(DiningZone::class, 'zone_uuid', 'uuid');
    }
}
