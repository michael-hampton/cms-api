<?php

namespace App\Controllers\Webhooks;

use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Services\Billing\Stripe\StripeWebhookService;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * POST /api/stripe/webhook
 *
 * Thin controller: verify signature, hand off to service.
 * Contains zero business logic.
 */
class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly StripeWebhookService $webhookService,
    ) {
        parent::__construct();
    }

    public function handle(Request $request): Response
    {
        $payload   = $request->getContent();

        $sigHeader = $request->header('Stripe-Signature');
        $secret    = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? config('payment.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (SignatureVerificationException $e) {
            return $this->errorResponse('Invalid signature', 400);
        } catch (\UnexpectedValueException $e) {
            return $this->errorResponse('Invalid payload', 400);
        }

        $this->webhookService->handle($event);

        return $this->successResponse('received');
    }
}