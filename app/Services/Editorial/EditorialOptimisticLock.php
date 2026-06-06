<?php

declare(strict_types=1);

namespace App\Services\Editorial;

use App\Exceptions\ApiException;
use App\Models\EditorialContent;
use Illuminate\Http\Request;

class EditorialOptimisticLock
{
    public function assertVersionMatches(EditorialContent $content, Request $request): void
    {
        $clientVersion = $this->resolveClientVersion($request);

        if ($clientVersion === null) {
            return;
        }

        $serverVersion = $content->updated_at?->toIso8601String();

        if ($clientVersion !== $serverVersion) {
            throw new ApiException(
                'VERSION_CONFLICT',
                'Il contenuto è stato modificato da un altro utente.',
                409,
                ['current_updated_at' => $serverVersion],
            );
        }
    }

    private function resolveClientVersion(Request $request): ?string
    {
        $ifMatch = $request->header('If-Match');

        if (is_string($ifMatch) && $ifMatch !== '') {
            return trim($ifMatch, '"');
        }

        $updatedAt = $request->input('updated_at');

        return is_string($updatedAt) && $updatedAt !== '' ? $updatedAt : null;
    }
}
