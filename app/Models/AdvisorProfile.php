<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'slug',
    'name',
    'title',
    'bio',
    'cta_label',
    'avatar_url',
    'calendly_url',
    'is_default',
    'is_active',
])]
class AdvisorProfile extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * B2C consumer payload (snake_case per API contracts).
     *
     * @return array<string, string|null>
     */
    public function toConsumerArray(): array
    {
        $payload = [
            'name' => $this->name,
            'role' => $this->title,
            'story' => $this->bio,
            'cta_label' => $this->cta_label,
        ];

        if ($this->avatar_url !== null) {
            $payload['avatar_url'] = $this->avatar_url;
        }

        if ($this->calendly_url !== null) {
            $payload['calendly_url'] = $this->calendly_url;
        }

        return $payload;
    }
}
