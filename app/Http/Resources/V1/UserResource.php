<?php

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Authenticated user profile.
 *
 * API contract:
 * - id: public uuid (RFC 4122)
 * - email: string
 * - name: string
 * - user_type: consumer | b2b | superadmin
 * - onboarding_status: nullable string (future B2B onboarding state)
 *
 * Hidden: internal id, password, remember_token, phone (unless explicitly needed)
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->uuid,
            'email' => $user->email,
            'name' => $user->name,
            'user_type' => $user->user_type->value,
            'onboarding_status' => $user->onboarding_status,
        ];
    }
}
