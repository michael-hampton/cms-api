<?php

namespace App\Tests\Functional\Controllers\Members\Api\Subscriptions;

use App\Enums\PaymentStatus;
use App\Models\Model;
use App\Models\Payment;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberSubscriptionPaymentsApiControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // =========================================================================
    // GET /api/member/subscription-payments
    // =========================================================================

    public function test_index_returns_empty_payments_when_no_active_subscription(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->getForSite('/api/member/subscription-payments', [], true);

        $this->assertResponseOk($response);
        $body = $this->decodeJson($response);
        $this->assertTrue($body['success']);
        $this->assertEmpty($body['payments']);
        $this->assertEquals(0, $body['paymentSummary']['total_count']);
        //$this->assertEquals('GBP', $body['paymentSummary']['currency']);
    }

    private function createAuthenticatedMember(array $overrides = [])
    {
        $member = $this->createMember($overrides);
        $this->actingAsMember($member);
        return $member;
    }

    public function test_index_returns_401_when_unauthenticated(): void
    {
        $this->unauthenticateMember();

        $response = $this->getForSite('/api/member/subscription-payments', [], true);

        $this->assertResponseStatus(401, $response);
    }

    public function test_index_returns_payments_for_active_subscription(): void
    {
        $member = $this->createAuthenticatedMember();
        $sub = $this->createSubscription(['member_id' => $member->id, 'status' => 'active']);
        $this->createPayment(['subscription_id' => $sub->id, 'status' => 'completed', 'amount' => 1000]);
        $this->createPayment(['subscription_id' => $sub->id, 'status' => 'completed', 'amount' => 1000]);

        $response = $this->getForSite('/api/member/subscription-payments', [], true);

        $this->assertResponseOk($response);
        $body = $this->decodeJson($response);

        $this->assertTrue($body['data']['success']);
        $this->assertCount(2, $body['data']['payments']);
    }

    public function createPayment(array $data): Model
    {
        return Payment::create([
            'order_id' => $data['order_id'] ?? null,
            'subscription_id' => $data['subscription_id'] ?? null,
            'site_id' => $data['site_id'] ?? $this->siteId,

            'payment_method' => $data['payment_method'] ?? 'manual',
            'payment_provider' => $data['payment_provider'] ?? null,

            'transaction_id' => $data['transaction_id'] ?? null,
            'payment_intent_id' => $data['payment_intent_id'] ?? null,

            'status' => $data['status'] ?? PaymentStatus::PENDING->value,
            'amount' => $data['amount'] ?? 0,
            'currency' => $data['currency'] ?? 'GBP',

            'metadata' => $data['metadata'] ?? null,
            'error_message' => $data['error_message'] ?? null,

            'paid_at' => ($data['status'] ?? null) === PaymentStatus::COMPLETED->value
                ? now()
                : null,

            'failed_at' => ($data['status'] ?? null) === PaymentStatus::FAILED->value
                ? now()
                : null,
        ]);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function test_index_payment_summary_counts_successful_and_failed(): void
    {
        $member = $this->createAuthenticatedMember();
        $sub = $this->createSubscription(['member_id' => $member->id, 'status' => 'active']);
        $this->createPayment(['subscription_id' => $sub->id, 'status' => 'completed', 'amount' => 500]);
        $this->createPayment(['subscription_id' => $sub->id, 'status' => 'completed', 'amount' => 500]);
        $this->createPayment(['subscription_id' => $sub->id, 'status' => 'failed', 'amount' => 500]);

        $response = $this->getForSite('/api/member/subscription-payments', [], true);

        $body = $this->decodeJson($response);
        $summary = $body['data']['paymentSummary'];

        $this->assertEquals(3, $summary['total_count']);
        $this->assertEquals(2, $summary['successful_count']);
        $this->assertEquals(1, $summary['failed_count']);
        $this->assertEquals(1000, $summary['total_paid']);
    }

    public function test_index_payment_summary_has_expected_keys(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->getForSite('/api/member/subscription-payments', [], true);

        $body = $this->decodeJson($response);
        $summary = $body['data']['paymentSummary'];

        foreach (['total_count', 'successful_count', 'failed_count', 'total_paid', 'currency'] as $key) {
            $this->assertArrayHasKey($key, $summary, "Missing summary key: {$key}");
        }
    }
}