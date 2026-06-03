<?php

namespace App\Enums;

enum TransactionType: string
{
    case Recharge = 'recharge';
    case LeadUnlock = 'lead_unlock';
    case Subscription = 'subscription';
    case LeadBundle = 'lead_bundle';
    case Commission = 'commission';
    case Refund = 'refund';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
