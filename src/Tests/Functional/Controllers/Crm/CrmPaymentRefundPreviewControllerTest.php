<?php

namespace App\Tests\Functional\Controllers\Crm;

use App\Models\Member;
use App\Models\Model;
use App\Models\Payment;
use App\Models\Subscription;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class CrmPaymentRefundPreviewControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;
    private Model $subscription;

    public function test_show_returns_subscription_refund_preview_for_member_payment(): void
    {
        $payment = $this->createSubscriptionPayment([
            'amount' => 30.00,
            'paid_at' => date('Y-m-d H:i:s', strtotime('-15 days')),
        ]);

        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/payments/' . $payment->id . '/refund-preview'
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('preview', $data);
        $this->assertEquals($payment->id, $data['preview']['payment_id']);
        $this->assertEquals($this->subscription->id, $data['preview']['subscription_id']);
        $this->assertEquals(30.00, $data['preview']['original_amount']);
        $this->assertEquals(30.00, $data['preview']['max_refundable_amount']);
        $this->assertContains('pro_rated', $data['preview']['available_actions']);
        $this->assertContains('manual', $data['preview']['available_actions']);
    }

    public function test_show_returns_401_for_unauthenticated_agent(): void
    {
        $payment = $this->createSubscriptionPayment();
        $this->unauthenticate();

        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/payments/' . $payment->id . '/refund-preview'
        );

        $this->assertResponseStatus(401, $response);
    }

    public function test_show_returns_404_for_unknown_payment(): void
    {
        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/payments/999999/refund-preview'
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_show_returns_404_when_payment_belongs_to_different_member(): void
    {
        $otherMember = $this->createMember();
        $otherSubscription = $this->createSubscriptionRecord(['member_id' => $otherMember->id]);
        $payment = $this->createSubscriptionPayment(['subscription_id' => $otherSubscription->id]);

        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/payments/' . $payment->id . '/refund-preview'
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_show_returns_422_for_order_payment_because_order_refunds_use_order_modal(): void
    {
        $order = $this->createOrder([
            'user_id' => $this->member->id,
            'site_id' => $this->siteId,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'site_id' => $this->siteId,
            'payment_method' => 'stripe',
            'payment_provider' => 'stripe',
            'amount' => 42.50,
            'currency' => 'GBP',
            'status' => 'completed',
            'paid_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/payments/' . $payment->id . '/refund-preview'
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('summary', $data);
        $this->assertSame('order', $data['summary']['mode']);
    }

    public function test_show_returns_404_for_subscription_on_different_site(): void
    {
        $otherSite = $this->createSite();
        $otherMember = $this->createMember(['site_id' => $otherSite->id]);
        $otherSubscription = $this->createSubscriptionRecord([
            'member_id' => $otherMember->id,
            'site_id' => $otherSite->id,
        ]);
        $payment = $this->createSubscriptionPayment([
            'subscription_id' => $otherSubscription->id,
            'site_id' => $otherSite->id,
        ]);

        $response = $this->getForSite(
            '/api/crm/members/' . $otherMember->id . '/payments/' . $payment->id . '/refund-preview'
        );

        $this->assertResponseStatus(404, $response);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = $this->createMember([
            'first_name' => 'Refund',
            'last_name' => 'Preview',
            'email' => 'refund.preview.' . uniqid() . '@example.com',
            'is_active' => true,
            'anonymous' => false,
        ]);

        $this->subscription = $this->createSubscriptionRecord([
            'member_id' => $this->member->id,
            'last_payment_date' => date('Y-m-d H:i:s', strtotime('-15 days')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+15 days')),
        ]);
    }

    private function createSubscriptionRecord(array $overrides = []): Model
    {
        return Subscription::create(array_merge([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Preview Plan',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s', strtotime('-15 days')),
            'last_payment_date' => date('Y-m-d H:i:s', strtotime('-15 days')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+15 days')),
            'next_billing_date' => date('Y-m-d H:i:s', strtotime('+15 days')),
            'price' => 30.00,
            'currency' => 'GBP',
            'auto_renew' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    private function createSubscriptionPayment(array $overrides = []): Model
    {
        return Payment::create(array_merge([
            'subscription_id' => $this->subscription->id,
            'site_id' => $this->siteId,
            'payment_method' => 'stripe',
            'payment_provider' => 'stripe',
            'transaction_id' => 'ch_' . uniqid(),
            'payment_intent_id' => 'pi_' . uniqid(),
            'stripe_invoice_id' => 'in_' . uniqid(),
            'amount' => 30.00,
            'currency' => 'GBP',
            'status' => 'completed',
            'paid_at' => date('Y-m-d H:i:s', strtotime('-15 days')),
            'created_at' => date('Y-m-d H:i:s', strtotime('-15 days')),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }
}
