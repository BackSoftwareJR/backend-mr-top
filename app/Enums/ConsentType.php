<?php

namespace App\Enums;

enum ConsentType: string
{
    case PrivacyPolicy = 'privacy_policy';
    case TermsB2c = 'terms_b2c';
    case Marketing = 'marketing';
    case AnalyticsCookies = 'analytics_cookies';
    case LeadSharing = 'lead_sharing';
    case TermsB2b = 'terms_b2b';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
