<?php

namespace App\Enums\Subscriptions;

/**
 * Internal representation of the Stripe events that trigger a payment
 * communication letter. Keeps raw Stripe event-type strings confined to the
 * webhook routing layer (StripeWebhookService::HANDLERS) instead of leaking
 * into services/listeners, per docs/domains/billing-stripe.md.
 */
enum PaymentCommunicationEventType: string
{
    case RENEWAL_INTENT_TO_DEBIT = 'invoice.upcoming';
    case PAYMENT_FAILED          = 'invoice.payment_failed';

    public function communicationType(): CommunicationTypeEnum
    {
        return match ($this) {
            self::RENEWAL_INTENT_TO_DEBIT => CommunicationTypeEnum::RENEWAL_INTENT_TO_DEBIT,
            self::PAYMENT_FAILED          => CommunicationTypeEnum::PAYMENT_FAILED_NOTICE,
        };
    }
}
