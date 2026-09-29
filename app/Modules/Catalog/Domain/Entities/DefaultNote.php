<?php

namespace Modules\Catalog\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasTranslations;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DefaultNote extends Model
{
    use HasUuid;
    use BelongsToTenant;
    use HasTranslations;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'text_translations',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'text_translations' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected array $translatableFields = ['text_translations'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function getTextAttribute(): string
    {
        return $this->translate('text_translations', null, 'Nota sin nombre');
    }
}
