<?php

namespace Modules\Catalog\Domain\Entities;

use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Regla de activación de una carta.
 * Define cuándo una carta está activa según canal, día y horario.
 * Se accede siempre a través del menú (que tiene tenant scope).
 */
class MenuActivation extends Model
{
    use HasUuid;

    public const CHANNEL_ALL = 'all';
    public const CHANNEL_DINE_IN = 'dine_in';
    public const CHANNEL_DELIVERY = 'delivery';
    public const CHANNEL_UBER_EATS = 'uber_eats';
    public const CHANNEL_RAPPI = 'rappi';

    protected $fillable = [
        'menu_id',
        'channel_type',
        'days_of_week',
        'time_from',
        'time_to',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}
