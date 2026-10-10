<?php

namespace Modules\Tables\Domain\Entities;

use App\Shared\Infrastructure\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Identity\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\Company;

class Area extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'code',
        'name_translations',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'name_translations' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function getNameAttribute(): string
    {
        $locale = app()->getLocale();
        return $this->name_translations[$locale] ?? $this->name_translations['es'] ?? $this->code;
    }
}
