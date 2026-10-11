<?php

namespace App\Shared\Domain\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Modules\Sync\Domain\ValueObjects\SyncStatus;

class SyncStatusCast implements CastsAttributes
{
    /**
     * Cast the given value.
     */
    public function get($model, string $key, $value, array $attributes): ?SyncStatus
    {
        if ($value === null) {
            return null;
        }
        
        return SyncStatus::tryFrom($value) ?? SyncStatus::PENDING;
    }

    /**
     * Prepare the given value for storage.
     */
    public function set($model, string $key, $value, array $attributes): string
    {
        if ($value instanceof SyncStatus) {
            return $value->value;
        }
        
        // Si es un string válido, devolverlo. Si no, default a 'pending'
        return SyncStatus::tryFrom($value)?->value ?? 'pending';
    }
}
