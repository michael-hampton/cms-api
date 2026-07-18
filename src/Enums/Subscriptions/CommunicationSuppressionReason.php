<?php

namespace App\Enums\Subscriptions;

enum CommunicationSuppressionReason: string
{
    case MEMBER_DECEASED = 'member_deceased';
    case MARKETING_CONSENT_NOT_GIVEN = 'marketing_consent_not_given';
    case MINOR_MARKETING_EXCLUDED = 'minor_marketing_excluded';
    case DO_NOT_MAIL = 'do_not_mail';
    case NO_MEMBER = 'no_member';
    case SCOPE_DISABLED = 'scope_disabled';
}
