<?php

namespace App\Tests\Unit\Actions\Stripe;

use App\Actions\Stripe\HandleInvoicePaid;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use PHPUnit\Framework\TestCase;

class HandleInvoicePaidTest extends FunctionalTestCase
{
    use CreatesTestData;
    protected function setUp(): void
    {
        parent::setUp();
    }

    private function makeInvoiceEvent(array $overrides = []): \Stripe\Event
    {
        $invoiceData = array_merge([
            'id'                  => 'in_test123',
            'object'              => 'invoice',
            'subscription'        => 'sub_test123',
            'payment_intent'      => 'pi_test123',
            'amount_paid'         => 1999,
            'amount_due'          => 1999,
            'currency'            => 'gbp',
            'hosted_invoice_url'  => 'https://invoice.stripe.com/inv_test',
            'status'              => 'paid',
            'status_transitions'  => ['paid_at' => time()],
            'lines'               => [
                'data' => [
                    [
                        'period' => ['end' => strtotime('+1 month')],
                    ],
                ],
            ],
            'last_finalization_error' => null,
        ], $overrides);

        return \Stripe\Event::constructFrom([
            'id'          => 'evt_test',
            'type'        => 'invoice.paid',
            'data'        => ['object' => $invoiceData],
            'api_version' => '2023-10-16',
        ]);
    }

    public function test_it_creates_a_payment_record_for_a_paid_invoice(): void
    {
        $subscription = $this->createSubscription([
            'payment_subscription_id' => 'sub_test123',
            'status'                 => 'past_due',
        ]);

        $event = $this->makeInvoiceEvent();

        (new HandleInvoicePaid())->handle($event);

        $this->assertDatabaseHas('payments', [
            'stripe_invoice_id' => 'in_test123',
            'status'            => PaymentStatus::COMPLETED->value,
            'amount'            => 19.99,
            'currency'          => 'GBP',
            'subscription_id'   => $subscription->id,
        ]);
    }

    public function test_it_sets_subscription_to_active_after_successful_payment(): void
    {
        $subscription = $this->createSubscription([
            'payment_subscription_id' => 'sub_test123',
            'status'                 => 'past_due',
        ]);

        $event = $this->makeInvoiceEvent();

        (new HandleInvoicePaid())->handle($event);

        $subscription->refresh();

        $this->assertSame('active', $subscription->status);
        $this->assertNotNull($subscription->last_payment_date);
    }

    public function test_it_extends_subscription_period_end(): void
    {
        $subscription = $this->createSubscription([
            'payment_subscription_id' => 'sub_test123',
        ]);

        $futureTimestamp = strtotime('+1 month');
        $event = $this->makeInvoiceEvent([
            'lines' => ['data' => [['period' => ['end' => $futureTimestamp]]]],
        ]);

        (new HandleInvoicePaid())->handle($event);

        $subscription->refresh();

        $this->assertSame(
            date('Y-m-d H:i:s', $futureTimestamp),
            $subscription->current_period_end->format('Y-m-d H:i:s')
        );
    }

    public function test_it_is_idempotent_on_duplicate_event(): void
    {
        $this->createSubscription(['payment_subscription_id' => 'sub_test123']);

        $event = $this->makeInvoiceEvent();

        (new HandleInvoicePaid())->handle($event);
        (new HandleInvoicePaid())->handle($event); // second delivery

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_it_handles_invoice_without_linked_subscription_gracefully(): void
    {
        $event = $this->makeInvoiceEvent(['subscription' => null]);

        (new HandleInvoicePaid())->handle($event);

        $this->assertDatabaseHas('payments', [
            'stripe_invoice_id' => 'in_test123',
            'subscription_id'   => null,
        ]);
    }
}