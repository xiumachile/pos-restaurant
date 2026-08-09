<?php

namespace App\Shared\Domain\Traits;

use Illuminate\Support\Str;

trait HasUuid
{
    /**
     * Boot del trait: genera UUID automáticamente al crear el registro.
     */
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Usar UUID como route key en URLs.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
