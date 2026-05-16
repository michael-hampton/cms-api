<?php

namespace App\Services\Billing\Stripe;

use App\Actions\Stripe\HandleInvoiceFailed;
use App\Actions\Stripe\HandleInvoicePaid;
use App\Actions\Stripe\HandleSubscriptionCreated;
use App\Actions\Stripe\HandleSubscriptionDeleted;
use App\Actions\Stripe\HandleSubscriptionUpdated;
use App\Framework\Support\Logger;
use App\Models\WebhookEvent;
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
    /** Map of Stripe event type → action class */
    private const HANDLERS = [
        'customer.subscription.created' => HandleSubscriptionCreated::class,
        'customer.subscription.updated' => HandleSubscriptionUpdated::class,
        'customer.subscription.deleted' => HandleSubscriptionDeleted::class,
        'invoice.paid'                  => HandleInvoicePaid::class,
        'invoice.payment_failed'        => HandleInvoiceFailed::class,
    ];

    public function handle(Event $event): void
    {
        // ── 1. Idempotency check ─────────────────────────────────────────────
        if (WebhookEvent::where('stripe_event_id', $event->id)->exists()) {
            Logger::info('StripeWebhookService: duplicate event ignored', [
                'stripe_event_id' => $event->id,
                'type'            => $event->type,
            ]);
            return;
        }

        // ── 2. Persist event record before processing ────────────────────────
        $webhookEvent = WebhookEvent::create([
            'stripe_event_id' => $event->id,
            'type'            => $event->type,
            'status'          => 'processed',
            'payload'         => $event->toArray(),
            'processed_at'    => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        // ── 3. Route to handler ──────────────────────────────────────────────
        $handlerClass = self::HANDLERS[$event->type] ?? null;

        if ($handlerClass === null) {
            $webhookEvent->status = 'ignored';
            $webhookEvent->save();

            Logger::info('StripeWebhookService: unhandled event type', [
                'type' => $event->type,
            ]);
            return;
        }

        // ── 4. Execute ───────────────────────────────────────────────────────
        try {
            /** @var object $handler */
            $handler = app($handlerClass);
            $handler->handle($event);
        } catch (\Throwable $e) {
            $webhookEvent->markFailed($e->getMessage());

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