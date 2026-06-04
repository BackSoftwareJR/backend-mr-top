<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\LeadMatch;

final class MarketplaceRef
{
    public static function fromMatch(LeadMatch $match): string
    {
        return $match->public_ref ?? self::fromMatchId($match->id);
    }

    public static function fromMatchId(int $leadMatchId): string
    {
        return 'ML-'.$leadMatchId;
    }

    public static function parseMatchId(string $ref): ?int
    {
        if (preg_match('/^ML-(\d+)$/', $ref, $matches) === 1) {
            return (int) $matches[1];
        }

        if (ctype_digit($ref)) {
            return (int) $ref;
        }

        return null;
    }

    public static function crmClientId(int $leadMatchId): string
    {
        return 'CRM-'.$leadMatchId;
    }

    public static function parseCrmClientId(string $ref): ?int
    {
        if (preg_match('/^CRM-(\d+)$/', $ref, $matches) === 1) {
            return (int) $matches[1];
        }

        if (ctype_digit($ref)) {
            return (int) $ref;
        }

        return null;
    }
}
