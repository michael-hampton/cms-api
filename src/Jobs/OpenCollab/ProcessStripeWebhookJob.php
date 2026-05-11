<?php

declare(strict_types=1);

namespace App\Jobs\OpenCollab;

use App\DTO\OpenCollab\StripeEvent;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Models\StripeWebhookEvent;
use App\Services\Billing\Stripe\StripeEventParser;
use App\Services\OpenCollab\StripeConnectWebhookHandler;
use App\Services\OpenCollab\StripeWebhookHandler;
use App\Services\Subscriptions\SubscriptionCancellationHandler;
use App\Services\Subscriptions\SubscriptionInvoiceHandler;
use Stripe\Event;

class ProcessStripeWebhookJob extends BaseJob
{
    public int $tries = 5;

    public function __construct(public readonly int $webhookEventRowId)
    {
    }

    public function handle(): void
    {
        $row = StripeWebhookEvent::find($this->webhookEventRowId);
        if (!$row) {
            return;
        }

        if (!empty($row->processed_at)) {
            return;
        }

        $event = Event::constructFrom((array)$row->payload_json);
        $correlationId = 'swh_' . ($event->id ?? (string)$row->stripe_event_id);

        try {
            $this->dispatchByType($event, $correlationId);
            $row->update([
                'processed_at' => date('Y-m-d H:i:s'),
                'failed_at' => null,
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            $row->update([
                'failed_at' => date('Y-m-d H:i:s'),
                'error_message' => $e->getMessage(),
            ]);

            /** @var Logger $logger */
            $logger = app(Logger::class);
            $logger->error('Stripe webhook processing failed.', [
                'event_id' => $row->stripe_event_id,
                'event_type' => $row->type,
                'correlation_id' => $correlationId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function dispatchByType(Event $event, string $correlationId): void
    {
        if (in_array($event->type, ['account.updated', 'payout.paid', 'payout.failed', 'transfer.created'], true)) {
            app(StripeConnectWebhookHandler::class)->handle($event, $correlationId);
            return;
        }

        match ($event->type) {
            'payment_intent.succeeded', 'payment_intent.payment_failed' => app(StripeWebhookHandler::class)->handle(
                new StripeEvent(
                    type: $event->type,
                    paymentIntentId: $event->data->object->id ?? null
                )
            ),
            'invoice.payment_succeeded' => app(SubscriptionInvoiceHandler::class)->handlePaymentSucceeded(
                app(StripeEventParser::class)->parseInvoice($event->type, $event->data->object)
            ),
            'invoice.payment_failed' => app(SubscriptionInvoiceHandler::class)->handlePaymentFailed(
                app(StripeEventParser::class)->parseInvoice($event->type, $event->data->object)
            ),
            'customer.subscription.deleted' => app(SubscriptionCancellationHandler::class)->handle(
                app(StripeEventParser::class)->parseSubscriptionDeleted($event->data->object)
            ),
            default => app(Logger::class)->info('Stripe webhook received (unhandled)', [
                'event_type' => $event->type,
                'correlation_id' => $correlationId,
            ]),
        };
    }
}

