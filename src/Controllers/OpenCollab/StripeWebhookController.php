<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Services\OpenCollab\ArticleAccessService;
use App\Services\OpenCollab\StripeWebhookHandler;
use App\Services\OpenCollab\StripeWebhookVerifier;
use Stripe\Exception\SignatureVerificationException;

/**
 * Receives and dispatches Stripe webhook events.
 *
 * Route must be unauthenticated (no AuthenticateWithToken middleware).
 * Webhook signature verification is mandatory — reject anything that doesn't verify.
 *
 * Handled events:
 *   payment_intent.succeeded      → grant access
 *   payment_intent.payment_failed → mark payment failed
 *
 * All other events return 200 immediately (Stripe expects 2xx or it retries).
 */
class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly ArticleAccessService  $accessService,
        private readonly Logger                $logger,
        private readonly StripeWebhookVerifier $stripeWebhookVerifier,
        private readonly StripeWebhookHandler  $stripeWebhookHandler
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

        if (!$event->paymentIntentId) {
            return $this->errorResponse('Missing payment_intent id.', 400);
        }

        try {
            $this->stripeWebhookHandler->handle($event);
        } catch (\RuntimeException $e) {
            $this->logger->error('Webhook handler failed.', [
                'event' => $event->type,
                'payment_intent' => $event->paymentIntentId,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Internal error processing webhook.', 500);
        }

        return $this->resourceResponse(['received' => true]);
    }
}