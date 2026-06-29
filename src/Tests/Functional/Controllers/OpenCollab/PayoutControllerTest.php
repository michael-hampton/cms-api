<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Enums\OpenCollab\AccrualStatus;
use App\Enums\OpenCollab\LedgerEntryType;
use App\Enums\OpenCollab\OnboardingStepStatus;
use App\Enums\OpenCollab\PayoutStatus;
use App\Models\ContributorOnboardingStep;
use App\Models\ContributorProfile;
use App\Models\EarningsLedger;
use App\Models\Payout;
use App\Models\Site;
use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PayoutControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private User $contributor;

    public function test_contributor_can_list_their_own_payouts(): void
    {
        $this->actingAs($this->contributor);

        Payout::create([
            'user_id' => $this->contributor->id,
            'site_id' => $this->siteId,
            'amount' => 7500,
            'currency' => 'GBP',
            'status' => PayoutStatus::Paid->value,
            'method' => 'bank_transfer',
        ]);

        $response = $this->getForSite('/api/open-collab/payouts');

        $data = json_decode($response->getContent(), true);
        $items = array_values(array_filter($data['data'], fn($k) => is_int($k), ARRAY_FILTER_USE_KEY));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $items);
        $this->assertEquals($this->contributor->id, $items[0]['user_id']);
    }

    public function test_contributor_cannot_see_other_contributors_payouts(): void
    {
        $other = $this->createUser([
            'email' => 'other-payout-contributor@example.com',
            'role' => 'contributor',
            'is_contributor' => true,
        ]);

        $this->actingAs($this->contributor);

        Payout::create([
            'user_id' => $other->id,
            'site_id' => $this->siteId,
            'amount' => 5000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Paid->value,
            'method' => 'bank_transfer',
        ]);

        $response = $this->getForSite('/api/open-collab/payouts');
        $data = json_decode($response->getContent(), true);
        $items = array_values(array_filter($data, fn($k) => is_int($k), ARRAY_FILTER_USE_KEY));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(0, $items);
    }

    public function test_balance_returns_available_balance_in_pence_and_pounds(): void
    {
        $this->actingAs($this->contributor);

        $page = $this->createPage(['contributor_id' => $this->contributor->id]);
        EarningsLedger::create([
            'user_id' => $this->contributor->id,
            'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value,
            'amount' => 12345,
            'currency' => 'GBP',
            'reference_id' => 'sale-1',
            'accrual_status' => AccrualStatus::Settled->value,
            'settled_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        $response = $this->getForSite('/api/open-collab/payouts/balance');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(12345, $data['data']['balance_pence']);
        $this->assertEquals('123.45', $data['data']['balance_pounds']);
    }

    public function test_contributor_can_request_payout(): void
    {
        $this->actingAs($this->contributor);

        $page = $this->createPage(['contributor_id' => $this->contributor->id]);
        EarningsLedger::create([
            'user_id' => $this->contributor->id,
            'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value,
            'amount' => 10000,
            'currency' => 'GBP',
            'reference_id' => 'sale-2',
            'accrual_status' => AccrualStatus::Settled->value,
            'settled_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        $this->setupSiteOnboarding();

        $response = $this->postForSite('/api/open-collab/payouts', [
            'method' => 'bank_transfer',
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertDatabaseHas('oc_payouts', [
            'user_id' => $this->contributor->id,
            'site_id' => $this->siteId,
            'amount' => 10000,
            'status' => PayoutStatus::Pending->value,
            'method' => 'bank_transfer',
        ]);

        $payout = Payout::where('user_id', $this->contributor->id)
            ->where('site_id', $this->siteId)
            ->first();

        $this->assertNotNull($payout);

        $this->assertDatabaseHas('oc_payout_ledger_entries', [
            'payout_id' => $payout->id,
            'amount' => 10000,
        ]);
    }

    public function test_contributor_cannot_request_payout_when_onboarding_incomplete(): void
    {
        $this->actingAs($this->contributor);

        $page = $this->createPage(['contributor_id' => $this->contributor->id]);
        EarningsLedger::create([
            'user_id' => $this->contributor->id,
            'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value,
            'amount' => 10000,
            'currency' => 'GBP',
            'reference_id' => 'sale-2-onboarding',
            'accrual_status' => AccrualStatus::Settled->value,
            'settled_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        $this->setupSiteOnboarding(true);

        $response = $this->postForSite('/api/open-collab/payouts', [
            'method' => 'bank_transfer',
        ]);

        $this->assertEquals(409, $response->getStatusCode());
    }

    public function test_request_returns_422_when_balance_is_below_minimum(): void
    {
        $this->actingAs($this->contributor);

        $page = $this->createPage(['contributor_id' => $this->contributor->id]);
        EarningsLedger::create([
            'user_id' => $this->contributor->id,
            'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value,
            'amount' => 4999,
            'currency' => 'GBP',
            'reference_id' => 'sale-3',
            'accrual_status' => AccrualStatus::Settled->value,
            'settled_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        $this->setupSiteOnboarding();

        $response = $this->postForSite('/api/open-collab/payouts', [
            'method' => 'bank_transfer',
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_unauthenticated_user_cannot_list_payouts(): void
    {
        $response = $this->getForSiteUnauthenticated('/api/open-collab/payouts');

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_admin_can_list_all_site_payouts(): void
    {
        Payout::create([
            'user_id' => $this->contributor->id,
            'site_id' => $this->siteId,
            'amount' => 9000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Pending->value,
            'method' => 'bank_transfer',
        ]);

        $response = $this->getForSite('/api/open-collab/admin/payouts');
        $data = json_decode($response->getContent(), true);
        $items = is_array($data['data'] ?? null) ? $data['data'] : $data;

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($items);
    }

    public function test_admin_list_returns_403_for_user_without_payout_view_permission(): void
    {
        $this->enableSiteRbac();

        $restrictedUser = $this->createUser([
            'email' => 'payout-admin-restricted@example.com',
            'role' => 'user',
        ]);
        $this->actingAs($restrictedUser);

        $response = $this->getForSite('/api/open-collab/admin/payouts');

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_request_returns_validation_errors_for_invalid_method(): void
    {
        $this->actingAs($this->contributor);

        $response = $this->postForSite('/api/open-collab/payouts', [
            'method' => 'cash',
        ]);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('Validation failed', $data['error']);
        $this->assertArrayHasKey('method', $data['errors']);
    }

    public function test_admin_can_approve_and_mark_payout_paid(): void
    {
        $payout = Payout::create([
            'user_id' => $this->contributor->id,
            'site_id' => $this->siteId,
            'amount' => 10000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Pending->value,
            'method' => 'bank_transfer',
        ]);

        $approveResponse = $this->postForSite("/api/open-collab/admin/payouts/{$payout->id}/approve");
        $this->assertEquals(200, $approveResponse->getStatusCode());


        $this->assertDatabaseHas('oc_payouts', ['id' => $payout->id, 'status' => PayoutStatus::Approved->value]);

        $paidResponse = $this->postForSite("/api/open-collab/admin/payouts/{$payout->id}/paid", [
            'reference' => 'BANK-123',
            'notes' => 'Paid in April batch.',
        ]);

        $this->assertEquals(200, $paidResponse->getStatusCode());
        $this->assertDatabaseHas('oc_payouts', [
            'id' => $payout->id,
            'status' => PayoutStatus::Paid->value,
            'reference' => 'BANK-123',
        ]);
    }

    public function test_approve_returns_403_for_user_without_payout_approve_permission(): void
    {
        $this->enableSiteRbac();

        $restrictedUser = $this->createUser([
            'email' => 'payout-approve-restricted@example.com',
            'role' => 'user',
        ]);
        $this->actingAs($restrictedUser);
        $this->grantSitePermission($restrictedUser, 'payout.view');

        $payout = Payout::create([
            'user_id' => $this->contributor->id,
            'site_id' => $this->siteId,
            'amount' => 10000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Pending->value,
            'method' => 'bank_transfer',
        ]);

        $response = $this->postForSite("/api/open-collab/admin/payouts/{$payout->id}/approve");

        $this->assertEquals(403, $response->getStatusCode());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->contributor = $this->createUser([
            'email' => 'payout-contributor@example.com',
            'role' => 'contributor',
            'is_contributor' => true,
        ]);

        ContributorProfile::firstOrCreate(
            ['user_id' => $this->contributor->id],
            [
                'bio' => 'This is a valid contributor bio for payout tests.',
                'minimum_age_confirmed' => true,
                'age_verified_at' => now_datetime()->format('Y-m-d H:i:s'),
                'age_verification_method' => 'test',
            ],
        );
    }

    public function test_admin_can_reject_pending_payout(): void
    {
        $payout = Payout::create([
            'user_id' => $this->contributor->id,
            'site_id' => $this->siteId,
            'amount' => 10000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Pending->value,
            'method' => 'paypal',
        ]);

        $response = $this->postForSite("/api/open-collab/admin/payouts/{$payout->id}/reject", [
            'reason' => 'Bank details are missing.',
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('oc_payouts', [
            'id' => $payout->id,
            'status' => PayoutStatus::Rejected->value,
            'rejection_reason' => 'Bank details are missing.',
        ]);
    }

    public function test_reject_returns_422_when_reason_is_missing(): void
    {
        $payout = Payout::create([
            'user_id' => $this->contributor->id,
            'site_id' => $this->siteId,
            'amount' => 10000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Pending->value,
            'method' => 'bank_transfer',
        ]);

        $response = $this->postForSite("/api/open-collab/admin/payouts/{$payout->id}/reject", []);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_mark_paid_returns_validation_errors_for_invalid_payload(): void
    {
        $payout = Payout::create([
            'user_id' => $this->contributor->id,
            'site_id' => $this->siteId,
            'amount' => 10000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Approved->value,
            'method' => 'bank_transfer',
        ]);

        $response = $this->postForSite("/api/open-collab/admin/payouts/{$payout->id}/paid", [
            'reference' => str_repeat('x', 256),
        ]);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('Validation failed', $data['error']);
        $this->assertArrayHasKey('reference', $data['errors']);
    }

    private function setupSiteOnboarding(bool $requiresSiteOnboarding = false): void
    {
        $this->ensureSiteExists();

        $site = Site::find($this->siteId);

        $site->update([
            'require_payment_setup' => $requiresSiteOnboarding,
            'require_contracts' => $requiresSiteOnboarding,
            'require_guidelines_ack' => $requiresSiteOnboarding,
            'require_age_verification' => $requiresSiteOnboarding,
        ]);

        ContributorProfile::where('user_id', $this->contributor->id)->update([
            'bio' => 'This is a valid contributor bio for payout tests.',
            'minimum_age_confirmed' => true,
            'age_verified_at' => now_datetime()->format('Y-m-d H:i:s'),
            'age_verification_method' => 'test',
        ]);

        if (!$requiresSiteOnboarding) {
            ContributorOnboardingStep::updateOrCreate(
                [
                    'user_id' => $this->contributor->id,
                    'site_id' => $this->siteId,
                    'step' => 'profile',
                ],
                [
                    'status' => OnboardingStepStatus::Completed->value,
                    'completed_at' => now_datetime()->format('Y-m-d H:i:s'),
                    'meta' => json_encode(['test' => true]),
                    'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
                ]
            );
        }
    }

}
