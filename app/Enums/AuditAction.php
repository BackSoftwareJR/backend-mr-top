<?php

declare(strict_types=1);

namespace App\Enums;

enum AuditAction: string
{
    case ImpersonateStart = 'admin.impersonate.start';
    case ImpersonateEnd = 'admin.impersonate.end';
    case PartnerApproved = 'partner.approved';
    case PartnerRejected = 'partner.rejected';
    case PartnerSuspended = 'partner.suspended';
    case LeadManualAssign = 'lead.manual_assign';
    case LeadReroute = 'lead.reroute';
    case PrivacyEraseRequest = 'privacy.erase_request';
    case PrivacyExport = 'privacy.export';
    case PrivacyErasureApproved = 'privacy.erasure.approved';
    case PrivacyErasureRejected = 'privacy.erasure.rejected';
    case PrivacyErasureReviewed = 'privacy.erasure.reviewed';
    case WalletRecharge = 'wallet.recharge';
    case LeadUnlocked = 'lead.unlocked';
    case CrmStatusUpdated = 'crm.status_updated';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
