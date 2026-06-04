<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Models\Site;
use App\Services\OpenCollab\ContributorOnboardingService;
use App\Services\OpenCollab\OpenCollabAuthorizationService;
use App\Services\OpenCollab\Policies\ContributorPolicy;
use App\Services\OpenCollab\Policies\ContributorPolicyService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class ContributorPolicyServiceTest extends FunctionalTestCase
{
    private ContributorPolicyService $service;
    private MockInterface $onboarding;
    private OpenCollabAuthorizationService $authorizationService;

    // ── canCreateArticle() ────────────────────────────────────────────────────

    public function test_allows_draft_creation_when_onboarding_incomplete(): void
    {
        $site = $this->makeSite();
        $this->onboarding->shouldNotReceive('pendingSteps');
        $this->onboarding->shouldNotReceive('isComplete');

        $this->assertTrue($this->service->canCreateArticle(1, $site));
    }

    private function makeSite(): Site
    {
        $site = new Site(['id' => 1, 'name' => 'Test Site']);
        $site->exists = true;
        return $site;
    }

    // ── canPublishArticle() ───────────────────────────────────────────────────

    public function test_allows_draft_creation_when_onboarding_complete(): void
    {
        $site = $this->makeSite();
        $this->onboarding->shouldNotReceive('pendingSteps');

        $this->assertTrue($this->service->canCreateArticle(1, $site));
    }

    public function test_blocks_publishing_when_contract_step_is_pending(): void
    {
        $site = $this->makeSite();

        $this->onboarding->shouldReceive('pendingSteps')->with(1, $site)->once()->andReturn([
            ['step' => 'contract', 'reason' => 'New contract requires signature.', 'meta' => ['contract_id' => 5]],
        ]);

        $this->assertFalse($this->service->canPublishArticle(1, $site));
    }

    public function test_blocks_publishing_when_profile_step_is_pending(): void
    {
        $site = $this->makeSite();

        $this->onboarding->shouldReceive('pendingSteps')->with(1, $site)->once()->andReturn([
            ['step' => 'profile', 'reason' => 'Bio is required.', 'meta' => []],
        ]);

        $this->assertFalse($this->service->canPublishArticle(1, $site));
    }

    public function test_blocks_publishing_when_guidelines_step_is_pending(): void
    {
        $site = $this->makeSite();

        $this->onboarding->shouldReceive('pendingSteps')->with(1, $site)->once()->andReturn([
            ['step' => 'guidelines', 'reason' => 'Guidelines updated.', 'meta' => ['required_version' => 2]],
        ]);

        $this->assertFalse($this->service->canPublishArticle(1, $site));
    }

    public function test_allows_publishing_when_only_payment_is_pending(): void
    {
        // Payment is NOT a publish-blocking step — a contributor can publish
        // without payout details set up.
        $site = $this->makeSite();

        $this->onboarding->shouldReceive('pendingSteps')->with(1, $site)->once()->andReturn([
            ['step' => 'payment', 'reason' => 'Payment details missing.', 'meta' => []],
        ]);

        $this->assertTrue($this->service->canPublishArticle(1, $site));
    }

    // ── canSubmitForReview() ──────────────────────────────────────────────────

    public function test_allows_publishing_when_no_steps_pending(): void
    {
        $site = $this->makeSite();

        $this->onboarding->shouldReceive('pendingSteps')->with(1, $site)->once()->andReturn([]);

        $this->assertTrue($this->service->canPublishArticle(1, $site));
    }

    public function test_blocks_submit_when_contract_pending(): void
    {
        $site = $this->makeSite();

        $this->onboarding->shouldReceive('pendingSteps')->with(1, $site)->once()->andReturn([
            ['step' => 'contract', 'reason' => 'Contract required.', 'meta' => []],
        ]);

        $this->assertFalse($this->service->canSubmitForReview(1, $site));
    }

    // ── canWithdraw() ─────────────────────────────────────────────────────────

    public function test_allows_submit_when_only_payment_pending(): void
    {
        $site = $this->makeSite();

        $this->onboarding->shouldReceive('pendingSteps')->with(1, $site)->once()->andReturn([
            ['step' => 'payment', 'reason' => 'Payment missing.', 'meta' => []],
        ]);

        $this->assertTrue($this->service->canSubmitForReview(1, $site));
    }

    public function test_blocks_withdrawal_when_payment_step_is_pending(): void
    {
        $site = $this->makeSite();

        $this->onboarding->shouldReceive('pendingSteps')->with(1, $site)->once()->andReturn([
            ['step' => 'payment', 'reason' => 'Payment details missing.', 'meta' => []],
        ]);

        $this->assertFalse($this->service->canWithdraw(1, $site));
    }

    public function test_blocks_withdrawal_when_contract_step_is_pending(): void
    {
        $site = $this->makeSite();

        $this->onboarding->shouldReceive('pendingSteps')->with(1, $site)->once()->andReturn([
            ['step' => 'contract', 'reason' => 'Contract required.', 'meta' => []],
        ]);

        $this->assertFalse($this->service->canWithdraw(1, $site));
    }

    // ── canReceiveEarnings() ──────────────────────────────────────────────────

    public function test_allows_withdrawal_when_no_steps_pending(): void
    {
        $site = $this->makeSite();

        $this->onboarding->shouldReceive('pendingSteps')->with(1, $site)->once()->andReturn([]);

        $this->assertTrue($this->service->canWithdraw(1, $site));
    }

    // ── Interface contract ────────────────────────────────────────────────────

    public function test_always_allows_receiving_earnings_regardless_of_onboarding(): void
    {
        $site = $this->makeSite();
        $this->onboarding->shouldNotReceive('pendingSteps');
        $this->onboarding->shouldNotReceive('isComplete');

        $this->assertTrue($this->service->canReceiveEarnings(1, $site));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function test_implements_contributor_policy_interface(): void
    {
        $this->assertInstanceOf(ContributorPolicy::class, $this->service);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->onboarding = Mockery::mock(ContributorOnboardingService::class);
        $this->authorizationService = Mockery::mock(OpenCollabAuthorizationService::class);

        $this->authorizationService->shouldReceive('allowsAny')->byDefault()->andReturn(true);
        $this->authorizationService->shouldReceive('allows')->byDefault()->andReturn(true);

        $this->service = new ContributorPolicyService($this->onboarding, $this->authorizationService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}