<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\OpenCollab;

use App\Framework\Http\Request;
use App\Services\OpenCollab\StripeWebhookVerifier;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookVerifierTest extends TestCase
{
    public function test_malformed_payload_is_rejected(): void
    {
        $_ENV['STRIPE_WEBHOOK_SECRET'] = 'whsec_unit_secret';
        $GLOBALS['__test_request_body'] = 'not-json';
        $_SERVER['HTTP_STRIPE_SIGNATURE'] = "t=" . time() . ",v1=invalid";
        $_SERVER['CONTENT_TYPE'] = 'application/json';

        $this->expectException(SignatureVerificationException::class);

        $verifier = new StripeWebhookVerifier();
        $verifier->verify(new Request());
    }
}

