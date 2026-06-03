<?php

namespace App\Models;

use App\Enums\OtpPortal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'email',
    'code_hash',
    'portal',
    'expires_at',
    'last_sent_at',
    'attempts',
])]
class OtpCode extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'portal' => OtpPortal::class,
            'expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'attempts' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
