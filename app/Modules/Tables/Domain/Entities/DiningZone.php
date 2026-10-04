<?php

namespace Modules\Tables\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasTranslations;
use App\Shared\Domain\Traits\HasUuid;
use App\Shared\Domain\Traits\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;

/**
 * Zona del restaurante (comedor, terraza, bar, VIP, etc.)
 *
 * Reemplaza evolutivamente al campo area_code de restaurant_tables.
 * Cada zona tiene un color distintivo y puede contener múltiples mesas.
 */
class DiningZone extends Model
{
    use HasUuid;
    use Syncable;
    use BelongsToTenant;
    use HasTranslations;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'code',
        'name_translations',
        'color',
        'floor_level',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'name_translations' => 'array',
        'floor_level' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected array $translatableFields = ['name_translations'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class, 'zone_id');
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('code');
    }

    // ============================================
    // HELPERS
    // ============================================

    public function getName(): string
    {
        return $this->translate('name_translations', null, $this->code);
    }

    public function activeTablesCount(): int
    {
        return $this->tables()->whereNull('deleted_at')->count();
    }
}
