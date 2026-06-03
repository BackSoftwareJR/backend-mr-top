<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Card = 'card';
    case Sepa = 'sepa';
    case Transfer = 'transfer';
    case Wallet = 'wallet';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
