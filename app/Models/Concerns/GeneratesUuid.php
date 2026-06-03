<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait GeneratesUuid
{
    protected static function bootGeneratesUuid(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
