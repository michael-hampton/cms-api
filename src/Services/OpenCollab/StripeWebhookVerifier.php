<?php

namespace App\Services\OpenCollab;

use App\Framework\Http\Request;
use Stripe\Webhook;

class StripeWebhookVerifier
{
    public function __construct(
        private readonly ?string $secret = null
    )
    {
    }

    public function verify(Request $request): object
    {
        $payload = $request->getContent(); // IMPORTANT: raw body
        if ($payload === '' && isset($GLOBALS['__test_request_body'])) {
            $payload = (string)$GLOBALS['__test_request_body'];
        }
        $sigHeader = $request->header('Stripe-Signature', '');
        $secret = (string)($this->secret ?: ($_ENV['STRIPE_WEBHOOK_SECRET'] ?? ''));

        return Webhook::constructEvent($payload, $sigHeader, $secret);
    }
}