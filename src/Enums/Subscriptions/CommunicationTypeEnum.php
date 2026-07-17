<?php

namespace App\Enums\Subscriptions;

enum CommunicationTypeEnum: string
{
    case RENEWAL_REMINDER  = 'renewal_reminder';
    case ACKNOWLEDGEMENT   = 'acknowledgement';
    case ITD               = 'itd';
    case CUSTOMER_CARE     = 'customer_care';
    case CCC_EXPIRY_REMINDER = 'ccc_expiry_reminder';

    // Renewal charge notice, triggered by Stripe's invoice.upcoming.
    // Distinct from ITD above, which is specifically the price-rise notice.
    case RENEWAL_INTENT_TO_DEBIT = 'renewal_intent_to_debit';

    // Triggered by Stripe's invoice.payment_failed.
    case PAYMENT_FAILED_NOTICE = 'payment_failed_notice';
}