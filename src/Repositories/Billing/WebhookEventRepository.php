<?php

namespace App\Repositories\Billing;

use App\Enums\Billing\WebhookEventStatus;
use App\Models\WebhookEvent;
use App\Repositories\Repository;

/**
 * webhook_events has no site_id column, so this repository is intentionally
 * global (not site-scoped) — see Repository::$filterBySite.
 */
class WebhookEventRepository extends Repository
{
    public function __construct()
    {
        parent::__construct();
        $this->filterBySite = false;
    }

    public function existsByStripeEventId(string $stripeEventId): bool
    {
        return WebhookEvent::where('stripe_event_id', $stripeEventId)->exists();
    }

    public function recordReceived(string $stripeEventId, string $type, array $payload): WebhookEvent
    {
        return WebhookEvent::create([
            'stripe_event_id' => $stripeEventId,
            'type'            => $type,
            'status'          => WebhookEventStatus::PROCESSED->value,
            'payload'         => $payload,
            'processed_at'    => now_datetime()->format('Y-m-d H:i:s'),
        ]);
    }

    public function markIgnored(WebhookEvent $webhookEvent): void
    {
        $webhookEvent->status = WebhookEventStatus::IGNORED->value;
        $webhookEvent->save();
    }

    public function markFailed(WebhookEvent $webhookEvent, string $errorMessage): void
    {
        $webhookEvent->markFailed($errorMessage);
    }

    protected function getModelClass(): string
    {
        return WebhookEvent::class;
    }
}
