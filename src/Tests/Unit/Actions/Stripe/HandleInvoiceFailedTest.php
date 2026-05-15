<?php

namespace App\Tests\Unit\Actions\Stripe;

use App\Actions\Stripe\HandleInvoiceFailed;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use PHPUnit\Framework\TestCase;

class HandleInvoiceFailedTest extends FunctionalTestCase
{
    use CreatesTestData;
    
    protected function setUp(): void
    {
        parent::setUp();
    }
    
    private function makeFailedInvoiceEvent(array $overrides = []): \Stripe\Event
    {
        $invoiceData = array_merge([
            'id'                     => 'in_fail123',
            'object'                 => 'invoice',
            'subscription'           => 'sub_fail123',
            'payment_intent'         => 'pi_fail123',
            'amount_due'             => 2999,
            'currency'               => 'gbp',
            'hosted_invoice_url'     => 'https://invoice.stripe.com/inv_fail',
            'status'                 => 'open',
            'last_finalization_error'=> ['message' => 'Your card has insufficient funds.'],
        ], $overrides);

        return \Stripe\Event::constructFrom([
            'id'          => 'evt_fail_test',
            'type'        => 'invoice.payment_failed',
            'data'        => ['object' => $invoiceData],
            'api_version' => '2023-10-16',
        ]);
    }

    public function test_it_creates_a_failed_payment_record(): void
    {
        $subscription = $this->createSubscription([
            'payment_subscription_id' => 'sub_fail123',
            'status'                 => 'active',
        ]);

        $event = $this->makeFailedInvoiceEvent();

        (new HandleInvoiceFailed())->handle($event);

        $this->assertDatabaseHas('payments', [
            'stripe_invoice_id' => 'in_fail123',
            'status'            => PaymentStatus::FAILED->value,
            'amount'            => 29.99,
        ]);
    }

    public function test_it_sets_subscription_status_to_past_due(): void
    {
        $subscription = $this->createSubscription([
            'payment_subscription_id' => 'sub_fail123',
            'status'                 => 'active',
        ]);

        $event = $this->makeFailedInvoiceEvent();

        (new HandleInvoiceFailed())->handle($event);

        $subscription->refresh();

        $this->assertSame('past_due', $subscription->status);
    }

    public function test_it_stores_the_stripe_error_message(): void
    {
        $this->createSubscription(['payment_subscription_id' => 'sub_fail123']);

        $event = $this->makeFailedInvoiceEvent([
            'last_finalization_error' => ['message' => 'Card declined.'],
        ]);

        (new HandleInvoiceFailed())->handle($event);

        $this->assertDatabaseHas('payments', [
            'stripe_invoice_id' => 'in_fail123',
            'error_message'     => 'Card declined.',
        ]);
    }

    public function test_it_is_idempotent_on_duplicate_event(): void
    {
        $this->createSubscription(['payment_subscription_id' => 'sub_fail123']);

        $event = $this->makeFailedInvoiceEvent();

        (new HandleInvoiceFailed())->handle($event);
        (new HandleInvoiceFailed())->handle($event);

        $this->assertDatabaseCount('payments', 1);
    }
}