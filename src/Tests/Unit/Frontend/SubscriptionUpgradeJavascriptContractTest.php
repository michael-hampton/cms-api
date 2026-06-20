<?php

namespace App\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;

final class SubscriptionUpgradeJavascriptContractTest extends TestCase
{
    private string $source;

    public function test_confirmation_is_followed_by_a_server_finalisation_request(): void
    {
        self::assertStringContainsString('const clientSecret =', $this->source);
        self::assertStringContainsString('const paymentIntentId =', $this->source);
        self::assertStringContainsString('await this.confirmPayment(clientSecret)', $this->source);
        self::assertStringContainsString('stripe.confirmCardPayment(clientSecret)', $this->source);
        self::assertStringContainsString('payment_intent_id: paymentIntentId', $this->source);
        self::assertStringContainsString("status: 'finalising'", $this->source);
    }

    public function test_failed_or_unverified_confirmation_is_not_reported_as_success(): void
    {
        self::assertStringContainsString('if (confirmation.error)', $this->source);
        self::assertStringContainsString("['succeeded', 'requires_capture'].includes(status)", $this->source);
        self::assertStringContainsString('Payment confirmation could not be verified.', $this->source);
        self::assertStringContainsString("status: 'error'", $this->source);
    }

    public function test_duplicate_submissions_are_blocked_during_confirmation_and_finalisation(): void
    {
        self::assertStringContainsString("'confirming_payment', 'finalising'", $this->source);
        self::assertStringContainsString("confirming_payment: 'Confirming payment…'", $this->source);
        self::assertStringContainsString("finalising: 'Finalising upgrade…'", $this->source);
    }

    public function test_stripe_uses_the_account_page_publishable_key(): void
    {
        self::assertStringContainsString('window.SubscriptionAccountStripeKey', $this->source);
        self::assertStringContainsString('window.Stripe(key)', $this->source);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $path = dirname(__DIR__, 3) . '/public/js/subscription-account-upgrade.js';
        $source = file_get_contents($path);
        self::assertNotFalse($source);
        $this->source = $source;
    }
}
