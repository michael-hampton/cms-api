<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Communications;

use App\Enums\Subscriptions\PaymentCommunicationEventType;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionCommunicationLetterCodeRepository;
use App\Repositories\Subscriptions\SubscriptionCommunicationRepository;

/**
 * Orchestrates the letter-only payment communications (renewal
 * intent-to-debit, payment failed) triggered by Stripe webhook events.
 * Mirrors SubscriptionItdCommunicationService's shape: resolve the active
 * communication by key, check eligibility, hand off to the sender.
 */
class PaymentCommunicationDispatchService
{
    private const COMMUNICATION_KEYS = [
        PaymentCommunicationEventType::RENEWAL_INTENT_TO_DEBIT->value => 'renewal_intent_to_debit_default',
        PaymentCommunicationEventType::PAYMENT_FAILED->value          => 'payment_failed_letter_default',
    ];

    public function __construct(
        private readonly SubscriptionCommunicationRepository $communications,
        private readonly SubscriptionCommunicationLetterCodeRepository $letterCodes,
        private readonly PaymentCommunicationEligibilityResolver $eligibility,
        private readonly SubscriptionCommunicationSender $sender,
    ) {
    }

    public function dispatch(
        PaymentCommunicationEventType $eventType,
        Subscription $subscription,
        array $metadata = [],
    ): void {
        $key = self::COMMUNICATION_KEYS[$eventType->value];
        $communication = $this->communications->findActiveByKey($key);

        if (!$communication) {
            throw new \RuntimeException(
                sprintf('Active payment communication [%s] was not found.', $key)
            );
        }

        $result = $this->eligibility->resolve($communication, $subscription, $subscription->member);

        if (!$result->eligible) {
            return;
        }

        $letterCode = $this->letterCodes->findForCommunication($communication->id);

        if (!$letterCode) {
            throw new \RuntimeException(
                sprintf('No letter code registered for communication [%s].', $key)
            );
        }

        $dedupeKey = $this->buildDedupeKey($eventType, $subscription, $metadata);

        $this->sender->send(
            subscription: $subscription,
            communication: $communication,
            metadata: array_merge($metadata, ['letter_code' => $letterCode->letter_code]),
            dedupeKey: $dedupeKey,
        );
    }

    /**
     * Scope dedupe per invoice (or billing period) so renewal ITD /
     * payment-failed letters can fire on later cycles — not once forever.
     */
    private function buildDedupeKey(
        PaymentCommunicationEventType $eventType,
        Subscription $subscription,
        array $metadata,
    ): string {
        $invoiceId = $metadata['invoice_id'] ?? null;

        if (is_string($invoiceId) && $invoiceId !== '') {
            return sprintf(
                '%s:subscription:%d:invoice:%s',
                $eventType->value,
                $subscription->id,
                $invoiceId,
            );
        }

        $periodEnd = $subscription->current_period_end ?? null;
        $periodKey = $periodEnd instanceof \DateTimeInterface
            ? $periodEnd->format('Y-m-d')
            : (is_string($periodEnd) && $periodEnd !== '' ? substr($periodEnd, 0, 10) : 'unknown');

        return sprintf(
            '%s:subscription:%d:period:%s',
            $eventType->value,
            $subscription->id,
            $periodKey,
        );
    }
}
