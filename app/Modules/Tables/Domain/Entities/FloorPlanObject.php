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
        'type',
        'position_x',
        'position_y',
        'rotation',
        'width',
        'height',
        'properties',
    ];
    
    protected $casts = [
        'properties' => 'array',
        'position_x' => 'integer',
        'position_y' => 'integer',
        'rotation' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];
    
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
    
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
