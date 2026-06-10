<?php

declare(strict_types=1);

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Jobs\OpenCollab\ProcessStripeWebhookJob;
use App\Models\StripeWebhookEvent;
use App\Services\OpenCollab\StripeWebhookVerifier;
use Stripe\Exception\SignatureVerificationException;
use Throwable;
use UnexpectedValueException;

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
        private readonly Logger                $logger,
        private readonly StripeWebhookVerifier $stripeWebhookVerifier,
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
        } catch (UnexpectedValueException $e) {
            $this->logger->warning('Stripe webhook payload could not be parsed.', [
                'error' => $e->getMessage(),
            ]);
            return $this->errorResponse('Invalid payload.', 400);
        }

        try {
            $existing = StripeWebhookEvent::where('stripe_event_id', $event->id)->first();

            if ($existing && !empty($existing->processed_at)) {
                $this->logger->info('metric.stripe_webhook.duplicate', [
                    'event_id' => $event->id ?? null,
                    'event_type' => $event->type ?? null,
                ]);
                return $this->resourceResponse(['received' => true]);
            }

            if (!$existing) {
                try {
                    $existing = StripeWebhookEvent::create([
                        'stripe_event_id' => (string)$event->id,
                        'type' => (string)$event->type,
                        'payload_json' => json_decode($request->getContent(), true) ?? [],
                    ]);
                } catch (Throwable) {
                    $existing = StripeWebhookEvent::where('stripe_event_id', $event->id)->first();
                }
            }

            $job = ProcessStripeWebhookJob::for((int)$existing->id)->onQueue('webhooks');

            if (($_ENV['APP_ENV'] ?? '') !== 'testing') {
                dispatch($job);
            }

            $this->logger->info('metric.stripe_webhook.accepted', [
                'event_id' => $event->id ?? null,
                'event_type' => $event->type ?? null,
            ]);

        } catch (Throwable $e) {
            $this->logger->error('Stripe webhook enqueue failed.', [
                'event_id' => $event->id ?? null,
                'event_type' => $event->type ?? null,
                'error' => $e->getMessage(),
            ]);
            $this->logger->error('metric.stripe_webhook.failed', [
                'event_id' => $event->id ?? null,
                'event_type' => $event->type ?? null,
            ]);
            return $this->errorResponse('Failed to queue webhook.', 500);
        }

        return $this->resourceResponse(['received' => true]);
    }

    public function adminIndex(): JsonResponse
    {
        if (!Auth::check()) {
            return $this->errorResponse('Not logged in', 401);
        }

        $events = StripeWebhookEvent::orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn(StripeWebhookEvent $event) => [
                'id' => $event->id,
                'stripe_event_id' => $event->stripe_event_id,
                'type' => $event->type,
                'processed_at' => $event->processed_at,
                'failed_at' => $event->failed_at,
                'error_message' => $event->error_message,
                'created_at' => $event->created_at,
            ]);

        return $this->jsonResponse($events->toArray());
    }
}