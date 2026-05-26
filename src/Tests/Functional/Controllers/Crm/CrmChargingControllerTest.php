<?php

namespace App\Tests\Functional\Controllers\Crm;

use App\Models\Member;
use App\Models\Payment;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

/**
 * Functional tests for CrmChargingController.
 *
 * Routes under test:
 *   POST /api/crm/members/{memberId}/charging/disable
 *   POST /api/crm/members/{memberId}/charging/enable
 *   POST /api/crm/members/{memberId}/payments/{paymentId}/retry
 *
 * Response shapes:
 *   disable / enable → { member: {...} }         200
 *   retry            → { payment: {...} }         200
 *   errors           → { error: '...', success: false }  422
 */
class CrmChargingControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;

    // ── disable ───────────────────────────────────────────────────────────────

    public function test_disable_returns_200_with_updated_member(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/charging/disable',
            ['reason' => 'Suspected fraud'],
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('member', $data);
    }

    public function test_disable_persists_charging_disabled_flag(): void
    {
        $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/charging/disable',
            ['reason' => 'Test reason'],
        );

        $this->assertDatabaseHas('members', [
            'id'               => $this->member->id,
            'charging_disabled' => 1,
        ]);
    }

    public function test_disable_persists_reason_when_provided(): void
    {
        $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/charging/disable',
            ['reason' => 'Chargeback risk'],
        );

        $this->assertDatabaseHas('members', [
            'id'                       => $this->member->id,
            'charging_disabled_reason' => 'Chargeback risk',
        ]);
    }

    public function test_disable_stores_null_reason_when_not_provided(): void
    {
        $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/charging/disable',
            [],
        );

        $this->assertDatabaseHas('members', [
            'id'                       => $this->member->id,
            'charging_disabled'        => 1,
            'charging_disabled_reason' => null,
        ]);
    }

    public function test_disable_returns_422_for_non_existent_member(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/999999/charging/disable',
            [],
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_disable_returns_401_for_unauthenticated_agent(): void
    {
        $this->unauthenticate();

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/charging/disable',
            [],
        );

        $this->assertResponseStatus(401, $response);
    }

    // ── enable ────────────────────────────────────────────────────────────────

    public function test_enable_returns_200_with_updated_member(): void
    {
        $this->disableChargingForMember();

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/charging/enable',
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('member', $data);
    }

    public function test_enable_clears_charging_disabled_flag(): void
    {
        $this->disableChargingForMember();

        $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/charging/enable',
        );

        $this->assertDatabaseHas('members', [
            'id'                       => $this->member->id,
            'charging_disabled'        => 0,
            'charging_disabled_reason' => null,
        ]);
    }

    public function test_enable_returns_422_for_non_existent_member(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/999999/charging/enable',
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_enable_returns_401_for_unauthenticated_agent(): void
    {
        $this->unauthenticate();

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/charging/enable',
        );

        $this->assertResponseStatus(401, $response);
    }

    // ── retryPayment ──────────────────────────────────────────────────────────

    public function test_retry_payment_returns_422_when_charging_is_disabled(): void
    {
        $this->disableChargingForMember();
        $payment = $this->createPayment(['member_id' => $this->member->id]);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/payments/' . $payment->id . '/retry',
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('disabled', strtolower($data['error']));
    }

    public function test_retry_payment_returns_422_for_non_existent_member(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/999999/payments/1/retry',
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_retry_payment_returns_401_for_unauthenticated_agent(): void
    {
        $this->unauthenticate();
        $payment = $this->createPayment(['member_id' => $this->member->id]);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/payments/' . $payment->id . '/retry',
        );

        $this->assertResponseStatus(401, $response);
    }

    // ── setup / helpers ───────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = $this->createMember([
            'first_name'        => 'Charge',
            'last_name'         => 'Tester',
            'email'             => 'charge.test.' . uniqid() . '@example.com',
            'is_active'         => true,
            'anonymous'         => false,
            'charging_disabled' => false,
        ]);
    }

    private function disableChargingForMember(): void
    {
        Member::where('id', $this->member->id)->update([
            'charging_disabled'        => true,
            'charging_disabled_reason' => 'Test',
            'charging_disabled_at'     => date('Y-m-d H:i:s'),
            'charging_disabled_by'     => 1,
        ]);
    }

    private function createPayment(array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'member_id'   => $this->member->id,
            'site_id'     => $this->siteId,
            'amount'      => 10.00,
            'currency'    => 'GBP',
            'received_at' => date('Y-m-d H:i:s'),
            'payment_method' => 'stripe'
        ], $overrides));
    }
}