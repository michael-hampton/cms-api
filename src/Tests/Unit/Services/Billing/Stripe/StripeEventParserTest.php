<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\DTO\Stripe\StripeInvoiceEvent;
use App\Services\Billing\Stripe\StripeEventParser;
use PHPUnit\Framework\TestCase;
use Stripe\Invoice;

class StripeEventParserTest extends TestCase
{
    private StripeEventParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new StripeEventParser();
    }

    public function test_parse_invoice_maps_billing_reason(): void
    {
        $invoice = Invoice::constructFrom([
            'id' => 'in_test123',
            'object' => 'invoice',
            'subscription' => 'sub_test123',
            'payment_intent' => 'pi_test123',
            'amount_paid' => 2900,
            'currency' => 'gbp',
            'billing_reason' => 'subscription_cycle',
            'period_start' => 1704067200,
            'period_end' => 1706745600,
            'lines' => ['data' => []],
        ]);

        $event = $this->parser->parseInvoice('invoice.paid', $invoice);

        $this->assertInstanceOf(StripeInvoiceEvent::class, $event);
        $this->assertSame('subscription_cycle', $event->billingReason);
        $this->assertTrue($event->isSubscriptionCycle());
    }

    public function test_parse_invoice_subscription_create_is_not_cycle(): void
    {
        $invoice = Invoice::constructFrom([
            'id' => 'in_create',
            'object' => 'invoice',
            'subscription' => 'sub_test123',
            'amount_paid' => 2900,
            'currency' => 'gbp',
            'billing_reason' => 'subscription_create',
            'period_start' => 1704067200,
            'period_end' => 1706745600,
            'lines' => ['data' => []],
        ]);

        $event = $this->parser->parseInvoice('invoice.paid', $invoice);

        $this->assertSame('subscription_create', $event->billingReason);
        $this->assertFalse($event->isSubscriptionCycle());
    }

    public function test_parse_invoice_null_billing_reason_is_not_cycle(): void
    {
        $invoice = Invoice::constructFrom([
            'id' => 'in_none',
            'object' => 'invoice',
            'subscription' => 'sub_test123',
            'amount_paid' => 2900,
            'currency' => 'gbp',
            'period_start' => 1704067200,
            'period_end' => 1706745600,
            'lines' => ['data' => []],
        ]);

        $event = $this->parser->parseInvoice('invoice.paid', $invoice);

        $this->assertNull($event->billingReason);
        $this->assertFalse($event->isSubscriptionCycle());
    }
}
