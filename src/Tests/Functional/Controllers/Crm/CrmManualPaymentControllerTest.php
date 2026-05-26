<?php

namespace App\Tests\Functional\Controllers\Crm;

use App\Enums\ManualPaymentType;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Model;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

/**
 * Functional tests for CrmManualPaymentController.
 *
 * Routes under test:
 *   GET    /api/crm/members/{memberId}/manual-payments          index
 *   POST   /api/crm/members/{memberId}/manual-payments          store
 *   DELETE /api/crm/members/{memberId}/manual-payments/{id}     destroy
 *
 * Response shapes:
 *   index   → { manual_payments: [...], pagination: {...} }    200
 *   store   → { manual_payment: {...} }                        201
 *   destroy → { success: true }                                200
 *   errors  → { error: '...', success: false }                 422 / 5xx
 */
class CrmManualPaymentControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;

    // ── index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_200_with_paginated_payments(): void
    {
        $this->createManualPayment(['member_id' => $this->member->id]);
        $this->createManualPayment(['member_id' => $this->member->id]);

        $response = $this->getForSite('/api/crm/members/' . $this->member->id . '/manual-payments');

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('manual_payments', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertCount(2, $data['manual_payments']);
    }

    public function test_index_returns_empty_list_when_member_has_no_manual_payments(): void
    {
        $response = $this->getForSite('/api/crm/members/' . $this->member->id . '/manual-payments');

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertCount(0, $data['manual_payments']);
        $this->assertEquals(0, $data['pagination']['total']);
    }

    public function test_index_returns_401_for_unauthenticated_agent(): void
    {
        $this->unauthenticate();

        $response = $this->getForSite('/api/crm/members/' . $this->member->id . '/manual-payments');

        $this->assertResponseStatus(401, $response);
    }

    public function test_index_only_returns_payments_for_requested_member(): void
    {
        $otherMember = $this->createMember();
        $this->createManualPayment(['member_id' => $this->member->id, 'reference' => 'MINE-001']);
        $this->createManualPayment(['member_id' => $otherMember->id, 'reference' => 'THEIRS-001']);

        $response = $this->getForSite('/api/crm/members/' . $this->member->id . '/manual-payments');

        $data       = json_decode($response->getContent(), true);
        $references = array_column($data['manual_payments'], 'reference');

        $this->assertContains('MINE-001', $references);
        $this->assertNotContains('THEIRS-001', $references);
    }

    public function test_index_paginates_payments(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->createManualPayment(['member_id' => $this->member->id]);
        }

        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/manual-payments?per_page=5&page=1'
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertCount(5, $data['manual_payments']);
        $this->assertEquals(20, $data['pagination']['total']);
        $this->assertEquals(4, $data['pagination']['last_page']);
    }

    public function test_index_returns_correct_pagination_metadata(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->createManualPayment(['member_id' => $this->member->id]);
        }

        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/manual-payments?per_page=10&page=1'
        );

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(1, $data['pagination']['current_page']);
        $this->assertEquals(10, $data['pagination']['per_page']);
        $this->assertEquals(1, $data['pagination']['last_page']);
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_manual_payment_and_returns_201(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/manual-payments',
            $this->validStorePayload(),
        );

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('manual_payment', $data);
        $this->assertNotEmpty($data['manual_payment']['id']);
    }

    public function test_store_persists_payment_in_database(): void
    {
        $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/manual-payments',
            $this->validStorePayload(['reference' => 'PERSIST-TEST']),
        );

        $this->assertDatabaseHas('payments', [
            'member_id' => $this->member->id,
            'reference' => 'PERSIST-TEST',
        ]);
    }

    public function test_store_returns_422_when_type_is_missing(): void
    {
        $payload = $this->validStorePayload();
        unset($payload['type']);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/manual-payments',
            $payload,
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_store_returns_422_when_amount_is_missing(): void
    {
        $payload = $this->validStorePayload();
        unset($payload['amount']);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/manual-payments',
            $payload,
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_store_returns_422_when_received_at_is_missing(): void
    {
        $payload = $this->validStorePayload();
        unset($payload['received_at']);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/manual-payments',
            $payload,
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_store_returns_422_when_amount_is_zero(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/manual-payments',
            $this->validStorePayload(['amount' => 0]),
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_store_returns_422_for_invalid_payment_type(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/manual-payments',
            $this->validStorePayload(['type' => 'not_a_valid_type']),
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_store_returns_422_for_non_existent_member(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/999999/manual-payments',
            $this->validStorePayload(),
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_store_returns_401_for_unauthenticated_agent(): void
    {
        $this->unauthenticate();

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/manual-payments',
            $this->validStorePayload(),
        );

        $this->assertResponseStatus(401, $response);
    }

    public function test_store_accepts_optional_subscription_id_and_order_id(): void
    {
        $subscription = $this->createSubscription(['member_id' => $this->member->id]);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/manual-payments',
            $this->validStorePayload([
                'subscription_id' => $subscription->id,
                'order_id'        => null,
            ]),
        );

        $this->assertResponseStatus(201, $response);

        $this->assertDatabaseHas('payments', [
            'member_id'       => $this->member->id,
            'subscription_id' => $subscription->id,
        ]);
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_deletes_manual_payment_and_returns_success(): void
    {
        $payment = $this->createManualPayment(['member_id' => $this->member->id]);

        $response = $this->deleteForSite(
            '/api/crm/members/' . $this->member->id . '/manual-payments/' . $payment->id
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
    }

    public function test_destroy_returns_422_when_payment_belongs_to_different_member(): void
    {
        $otherMember = $this->createMember();
        $payment     = $this->createManualPayment(['member_id' => $otherMember->id]);

        $response = $this->deleteForSite(
            '/api/crm/members/' . $this->member->id . '/manual-payments/' . $payment->id
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_destroy_returns_422_when_payment_not_found(): void
    {
        $response = $this->deleteForSite(
            '/api/crm/members/' . $this->member->id . '/manual-payments/999999'
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_destroy_returns_401_for_unauthenticated_agent(): void
    {
        $this->unauthenticate();
        $payment = $this->createManualPayment(['member_id' => $this->member->id]);

        $response = $this->deleteForSite(
            '/api/crm/members/' . $this->member->id . '/manual-payments/' . $payment->id
        );

        $this->assertResponseStatus(401, $response);
    }

    // ── setup / helpers ───────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = $this->createMember([
            'first_name' => 'Payment',
            'last_name'  => 'Tester',
            'email'      => 'manual.payment.' . uniqid() . '@example.com',
            'is_active'  => true,
            'anonymous'  => false,
        ]);
    }

    private function createManualPayment(array $overrides = []): Model
    {
        return Payment::create(array_merge([
            'member_id'      => $this->member->id,
            'site_id'        => $this->siteId,
            'payment_method' => ManualPaymentType::CASH->value,
            'amount'         => 25.00,
            'currency'       => 'GBP',
            'reference'      => 'REF-' . uniqid(),
            'received_at'    => date('Y-m-d H:i:s'),
            'created_by'     => 1,
        ], $overrides));
    }

    private function validStorePayload(array $overrides = []): array
    {
        return array_merge([
            'type'        => ManualPaymentType::CASH->value,
            'amount'      => 49.99,
            'currency'    => 'GBP',
            'reference'   => 'REF-TEST-' . uniqid(),
            'notes'       => 'Paid in person',
            'received_at' => date('Y-m-d H:i:s'),
        ], $overrides);
    }
}