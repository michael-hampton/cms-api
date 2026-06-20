<?php

namespace App\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;

final class SubscriptionUpgradeJavascriptContractTest extends TestCase
{
    private string $source;

    public function test_client_secret_requires_stripe_confirmation_before_success(): void
    {
        self::assertStringContainsString('const clientSecret =', $this->source);
        self::assertStringContainsString('await this.confirmPayment(clientSecret)', $this->source);
        self::assertStringContainsString('stripe.confirmCardPayment(clientSecret)', $this->source);
    }

    public function test_failed_or_incomplete_payment_does_not_report_upgrade_success(): void
    {
        self::assertStringContainsString('if (confirmation.error)', $this->source);
        self::assertStringContainsString("['succeeded', 'processing', 'requires_capture'].includes(status)", $this->source);
        self::assertStringContainsString("state: 'error'", $this->source);
    }

    public function test_duplicate_upgrade_submissions_are_blocked_during_payment_confirmation(): void
    {
        self::assertStringContainsString("['submitting', 'confirming_payment'].includes(this.state.status)", $this->source);
        self::assertStringContainsString("confirming_payment: 'Confirming payment…'", $this->source);
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
