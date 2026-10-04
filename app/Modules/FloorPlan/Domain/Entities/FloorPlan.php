<?php

namespace Modules\FloorPlan\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use App\Shared\Domain\Traits\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;

/**
 * Plano de un salón del restaurante (Sección 8.1 de la spec)
 */
class FloorPlan extends Model
{
    use HasUuid;
    use Syncable;
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'slug',
        'width',
        'height',
        'scale',
        'background',
        'settings',
        'version',
        'status',
        'published_at',
        'published_by',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'scale' => 'decimal:2',
        'background' => 'array',
        'settings' => 'array',
        'version' => 'integer',
        'published_at' => 'datetime',
    ];

    // Valores por defecto para atributos con defaults en BD
    protected $attributes = [
        'width' => 1200,
        'height' => 1800,
        'scale' => '100.00',
        'version' => 1,
        'status' => 'draft',
    ];

    // Estados posibles
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function objects(): HasMany
    {
        return $this->hasMany(FloorPlanObject::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(\Modules\Identity\Domain\Entities\User::class, 'published_by');
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeDrafts($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    // Helpers
    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function publish(): self
    {
        $this->status = self::STATUS_PUBLISHED;
        $this->published_at = now();
        return $this;
    }
}
