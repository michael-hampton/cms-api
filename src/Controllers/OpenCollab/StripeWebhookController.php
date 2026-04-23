<?php

declare(strict_types=1);

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Services\Billing\Stripe\StripeEventParser;
use App\Services\OpenCollab\StripeWebhookHandler;
use App\Services\OpenCollab\StripeWebhookVerifier;
use App\Services\Subscriptions\SubscriptionCancellationHandler;
use App\Services\Subscriptions\SubscriptionInvoiceHandler;
use Stripe\Exception\SignatureVerificationException;

/**
 * Receives and dispatches Stripe webhook events.
 *
 * Route must be unauthenticated (no AuthenticateWithToken middleware).
 * Webhook signature verification is mandatory — reject anything that doesn't verify.
 *
 * Handled events:
 *   payment_intent.succeeded         → grant access (existing flow)
 *   payment_intent.payment_failed    → mark payment failed (existing flow)
 *   invoice.payment_succeeded        → record payment, update billing, emit event
 *   invoice.payment_failed           → record failure, set PAST_DUE, emit event
 *   customer.subscription.deleted    → mark cancelled, emit event
 *
 * All other events return 200 immediately (Stripe expects 2xx or it retries).
 */
class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly Logger                          $logger,
        private readonly StripeWebhookVerifier           $stripeWebhookVerifier,
        private readonly StripeWebhookHandler            $stripeWebhookHandler,
        private readonly StripeEventParser               $stripeEventParser,
        private readonly SubscriptionInvoiceHandler      $invoiceHandler,
        private readonly SubscriptionCancellationHandler $cancellationHandler,
    )
    {
        parent::__construct();
    }

    public function handle(Request $request): JsonResponse
    {
        try {
            $event = $this->stripeWebhookVerifier->verify($request);
        } catch (SignatureVerificationException $e) {
            $this->logger->warning('Stripe webhook signature verification failed.', [
                'error' => $e->getMessage(),
            ]);
            return $this->errorResponse('Invalid signature.', 400);
        } catch (\UnexpectedValueException $e) {
            $this->logger->warning('Stripe webhook payload could not be parsed.', [
                'error' => $e->getMessage(),
            ]);
            return $this->errorResponse('Invalid payload.', 400);
        }

        try {
            $this->dispatch($event);
        } catch (\RuntimeException $e) {
            $this->logger->error('Webhook handler failed.', [
                'event' => $event->type,
                'error' => $e->getMessage(),
            ]);
            return $this->errorResponse('Internal error processing webhook.', 500);
        }

        return $this->resourceResponse(['received' => true]);
    }

    // ── Internal routing ───────────────────────────────────────────────────

    private function dispatch(object $event): void
    {
        match ($event->type) {
            // ── Existing payment_intent flow ──────────────────────────────
            'payment_intent.succeeded',
            'payment_intent.payment_failed' => $this->handlePaymentIntent($event),

            // ── Invoice events ────────────────────────────────────────────
            'invoice.payment_succeeded' => $this->handleInvoiceSucceeded($event),
            'invoice.payment_failed' => $this->handleInvoiceFailed($event),

            // ── Subscription lifecycle ────────────────────────────────────
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event),

            // ── All other events: acknowledge and ignore ──────────────────
            default => $this->logger->info('Stripe webhook received (unhandled)', [
                'event_type' => $event->type,
            ]),
        };
    }

    private function handlePaymentIntent(object $event): void
    {
        if (!$event->paymentIntentId) {
            throw new \RuntimeException('Missing payment_intent id.');
        }

        $this->stripeWebhookHandler->handle($event);
    }

    private function handleInvoiceSucceeded(object $event): void
    {
        /** @var \Stripe\Invoice $invoice */
        $invoice = $event->data->object;
        $invoiceEvent = $this->stripeEventParser->parseInvoice($event->type, $invoice);

        $this->invoiceHandler->handlePaymentSucceeded($invoiceEvent);
    }

    private function handleInvoiceFailed(object $event): void
    {
        /** @var \Stripe\Invoice $invoice */
        $invoice = $event->data->object;
        $invoiceEvent = $this->stripeEventParser->parseInvoice($event->type, $invoice);

        $this->invoiceHandler->handlePaymentFailed($invoiceEvent);
    }

    private function handleSubscriptionDeleted(object $event): void
    {
        /** @var \Stripe\Subscription $stripeSubscription */
        $stripeSubscription = $event->data->object;
        $deletedEvent = $this->stripeEventParser->parseSubscriptionDeleted($stripeSubscription);

        $this->cancellationHandler->handle($deletedEvent);
    }
}