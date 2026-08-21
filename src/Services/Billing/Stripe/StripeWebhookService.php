<?php

namespace App\Services\Billing\Stripe;

use App\Actions\Stripe\HandleInvoiceFailed;
use App\Actions\Stripe\HandleInvoicePaid;
use App\Actions\Stripe\HandleInvoiceUpcoming;
use App\Actions\Stripe\HandleSubscriptionCreated;
use App\Actions\Stripe\HandleSubscriptionDeleted;
use App\Actions\Stripe\HandleSubscriptionUpdated;
use App\Framework\Support\Logger;
use App\Repositories\Billing\WebhookEventRepository;
use Stripe\Event;

/**
 * Routes incoming Stripe events to the correct action class.
 *
 * Responsibilities:
 *  1. Idempotency — ignore events we have already processed.
 *  2. Persistence — record every event before processing begins.
 *  3. Routing — delegate to a dedicated action class per event type.
 *  4. Observability — mark events failed and log when processing throws.
 */
class StripeWebhookService
{
    public function __construct(
        private readonly WebhookEventRepository    $webhookEventRepository,
        private readonly HandleSubscriptionCreated $handleSubscriptionCreated,
        private readonly HandleSubscriptionUpdated $handleSubscriptionUpdated,
        private readonly HandleSubscriptionDeleted $handleSubscriptionDeleted,
        private readonly HandleInvoicePaid         $handleInvoicePaid,
        private readonly HandleInvoiceFailed       $handleInvoiceFailed,
        private readonly HandleInvoiceUpcoming     $handleInvoiceUpcoming,
    ) {
    }

    /** Map of Stripe event type → handler instance */
    private function handlers(): array
    {
        return [
            'customer.subscription.created' => $this->handleSubscriptionCreated,
            'customer.subscription.updated' => $this->handleSubscriptionUpdated,
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted,
            'invoice.paid'                  => $this->handleInvoicePaid,
            'invoice.payment_failed'        => $this->handleInvoiceFailed,
            'invoice.upcoming'              => $this->handleInvoiceUpcoming,
        ];
    }

    public function handle(Event $event): void
    {
        // ── 1. Idempotency check ─────────────────────────────────────────────
        if ($this->webhookEventRepository->existsByStripeEventId($event->id)) {
            Logger::info('StripeWebhookService: duplicate event ignored', [
                'stripe_event_id' => $event->id,
                'type'            => $event->type,
            ]);
            return;
        }

        // ── 2. Persist event record before processing ────────────────────────
        $webhookEvent = $this->webhookEventRepository->recordReceived(
            $event->id,
            $event->type,
            $event->toArray()
        );

        // ── 3. Route to handler ──────────────────────────────────────────────
        $handler = $this->handlers()[$event->type] ?? null;

        if ($handler === null) {
            $this->webhookEventRepository->markIgnored($webhookEvent);

            Logger::info('StripeWebhookService: unhandled event type', [
                'type' => $event->type,
            ]);
            return;
        }

        // ── 4. Execute ───────────────────────────────────────────────────────
        try {
            $handler->handle($event);
        } catch (\Throwable $e) {
            $this->webhookEventRepository->markFailed($webhookEvent, $e->getMessage());

            Logger::error('StripeWebhookService: handler threw exception', [
                'stripe_event_id' => $event->id,
                'type'            => $event->type,
                'error'           => $e->getMessage(),
                'trace'           => $e->getTraceAsString(),
            ]);

            // Re-throw so the controller can return a 500, prompting Stripe to
            // retry the delivery.
            throw $e;
        }
    }
}
