<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Enums\OpenCollab\OnboardingStepStatus;
use App\Models\ContributorOnboardingStep;
use App\Repositories\OpenCollab\ContributorOnboardingStepRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class ContributorOnboardingStepRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private ContributorOnboardingStepRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ContributorOnboardingStepRepository();
    }

    // ── markCompleted ─────────────────────────────────────────────────────────

    public function test_mark_completed_creates_row_when_none_exists(): void
    {
        $user = $this->createUser();

        $this->repository->markCompleted($user->id, $this->siteId, 'profile');

        $this->assertDatabaseHas('oc_contributor_onboarding_steps', [
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'step'    => 'profile',
            'status'  => OnboardingStepStatus::Completed->value,
        ]);
    }

    public function test_mark_completed_updates_existing_row(): void
    {
        $user = $this->createUser();

        ContributorOnboardingStep::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'step'    => 'profile',
            'status'  => OnboardingStepStatus::InProgress->value,
        ]);

        $this->repository->markCompleted($user->id, $this->siteId, 'profile');

        $this->assertDatabaseHas('oc_contributor_onboarding_steps', [
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'step'    => 'profile',
            'status'  => OnboardingStepStatus::Completed->value,
        ]);
        // Only one row should exist — no duplicate created.
        $this->assertDatabaseCount('oc_contributor_onboarding_steps', 1);
    }

    public function test_mark_completed_sets_completed_at_timestamp(): void
    {
        $user = $this->createUser();

        $this->repository->markCompleted($user->id, $this->siteId, 'contract');

        $row = ContributorOnboardingStep::where('user_id', $user->id)
            ->where('site_id', $this->siteId)
            ->where('step', 'contract')
            ->first();

        $this->assertNotNull($row->completed_at);
    }

    public function test_mark_completed_stores_meta_as_json(): void
    {
        $user = $this->createUser();
        $meta = ['contract_id' => 7, 'contract_version' => 3];

        $this->repository->markCompleted($user->id, $this->siteId, 'contract', $meta);

        $row = ContributorOnboardingStep::where('user_id', $user->id)
            ->where('site_id', $this->siteId)
            ->where('step', 'contract')
            ->first();

        $this->assertEquals($meta, $row->completed_meta);
    }

    public function test_mark_completed_is_idempotent(): void
    {
        $user = $this->createUser();

        $this->repository->markCompleted($user->id, $this->siteId, 'guidelines');
        $this->repository->markCompleted($user->id, $this->siteId, 'guidelines');

        $this->assertDatabaseCount('oc_contributor_onboarding_steps', 1);
        $this->assertDatabaseHas('oc_contributor_onboarding_steps', [
            'status' => OnboardingStepStatus::Completed->value,
        ]);
    }

    // ── markPending ───────────────────────────────────────────────────────────

    public function test_mark_pending_creates_row_when_none_exists(): void
    {
        $user = $this->createUser();

        $this->repository->markPending($user->id, $this->siteId, 'payment');

        $this->assertDatabaseHas('oc_contributor_onboarding_steps', [
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'step'    => 'payment',
            'status'  => OnboardingStepStatus::Pending->value,
        ]);
    }

    public function test_mark_pending_updates_existing_completed_row(): void
    {
        $user = $this->createUser();

        ContributorOnboardingStep::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'step'    => 'payment',
            'status'  => OnboardingStepStatus::Completed->value,
        ]);

        $this->repository->markPending($user->id, $this->siteId, 'payment');

        $this->assertDatabaseHas('oc_contributor_onboarding_steps', [
            'user_id' => $user->id,
            'status'  => OnboardingStepStatus::Pending->value,
        ]);
    }

    // ── markInProgress ────────────────────────────────────────────────────────

    public function test_mark_in_progress_creates_row_when_none_exists(): void
    {
        $user = $this->createUser();

        $this->repository->markInProgress($user->id, $this->siteId, 'profile');

        $this->assertDatabaseHas('oc_contributor_onboarding_steps', [
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'step'    => 'profile',
            'status'  => OnboardingStepStatus::InProgress->value,
        ]);
    }

    public function test_mark_in_progress_does_not_downgrade_completed_row(): void
    {
        $user = $this->createUser();

        ContributorOnboardingStep::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'step'    => 'profile',
            'status'  => OnboardingStepStatus::Completed->value,
        ]);

        $this->repository->markInProgress($user->id, $this->siteId, 'profile');

        // Should remain completed — in_progress must never override completed.
        $this->assertDatabaseHas('oc_contributor_onboarding_steps', [
            'user_id' => $user->id,
            'status'  => OnboardingStepStatus::Completed->value,
        ]);
    }

    // ── markInvalidated ───────────────────────────────────────────────────────

    public function test_mark_invalidated_transitions_completed_row(): void
    {
        $user = $this->createUser();

        ContributorOnboardingStep::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'step'    => 'contract',
            'status'  => OnboardingStepStatus::Completed->value,
        ]);

        $this->repository->markInvalidated($user->id, $this->siteId, 'contract');

        $this->assertDatabaseHas('oc_contributor_onboarding_steps', [
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'step'    => 'contract',
            'status'  => OnboardingStepStatus::Invalidated->value,
        ]);
    }

    public function test_mark_invalidated_is_noop_for_pending_row(): void
    {
        $user = $this->createUser();

        ContributorOnboardingStep::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'step'    => 'contract',
            'status'  => OnboardingStepStatus::Pending->value,
        ]);

        $this->repository->markInvalidated($user->id, $this->siteId, 'contract');

        // Pending rows must not be changed.
        $this->assertDatabaseHas('oc_contributor_onboarding_steps', [
            'user_id' => $user->id,
            'status'  => OnboardingStepStatus::Pending->value,
        ]);
    }

    public function test_mark_invalidated_is_noop_when_no_row_exists(): void
    {
        $user = $this->createUser();

        // Must not throw — idempotent no-op when row is absent.
        $this->repository->markInvalidated($user->id, $this->siteId, 'contract');

        $this->assertDatabaseCount('oc_contributor_onboarding_steps', 0);
    }

    public function test_mark_invalidated_is_noop_for_in_progress_row(): void
    {
        $user = $this->createUser();

        ContributorOnboardingStep::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'step'    => 'guidelines',
            'status'  => OnboardingStepStatus::InProgress->value,
        ]);

        $this->repository->markInvalidated($user->id, $this->siteId, 'guidelines');

        $this->assertDatabaseHas('oc_contributor_onboarding_steps', [
            'status' => OnboardingStepStatus::InProgress->value,
        ]);
    }

    // ── isCompleted ───────────────────────────────────────────────────────────

    public function test_is_completed_returns_true_only_for_completed_status(): void
    {
        $user = $this->createUser();

        ContributorOnboardingStep::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'step'    => 'profile',
            'status'  => OnboardingStepStatus::Completed->value,
        ]);

        $this->assertTrue($this->repository->isCompleted($user->id, $this->siteId, 'profile'));
    }

    public function test_is_completed_returns_false_for_pending_status(): void
    {
        $user = $this->createUser();

        ContributorOnboardingStep::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'step'    => 'profile',
            'status'  => OnboardingStepStatus::Pending->value,
        ]);

        $this->assertFalse($this->repository->isCompleted($user->id, $this->siteId, 'profile'));
    }

    public function test_is_completed_returns_false_for_invalidated_status(): void
    {
        $user = $this->createUser();

        ContributorOnboardingStep::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'step'    => 'contract',
            'status'  => OnboardingStepStatus::Invalidated->value,
        ]);

        $this->assertFalse($this->repository->isCompleted($user->id, $this->siteId, 'contract'));
    }

    public function test_is_completed_returns_false_when_no_row_exists(): void
    {
        $user = $this->createUser();

        $this->assertFalse($this->repository->isCompleted($user->id, $this->siteId, 'profile'));
    }

    // ── getStatus ─────────────────────────────────────────────────────────────

    public function test_get_status_returns_correct_status_string(): void
    {
        $user = $this->createUser();

        ContributorOnboardingStep::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'step'    => 'payment',
            'status'  => OnboardingStepStatus::InProgress->value,
        ]);

        $this->assertSame(
            OnboardingStepStatus::InProgress->value,
            $this->repository->getStatus($user->id, $this->siteId, 'payment')
        );
    }

    public function test_get_status_returns_null_when_no_row_exists(): void
    {
        $user = $this->createUser();

        $this->assertNull($this->repository->getStatus($user->id, $this->siteId, 'payment'));
    }

    // ── getStep ───────────────────────────────────────────────────────────────

    public function test_get_step_returns_full_row(): void
    {
        $user = $this->createUser();

        ContributorOnboardingStep::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'step'    => 'guidelines',
            'status'  => OnboardingStepStatus::Completed->value,
        ]);

        $row = $this->repository->getStep($user->id, $this->siteId, 'guidelines');

        $this->assertInstanceOf(ContributorOnboardingStep::class, $row);
        $this->assertSame('guidelines', $row->step);
        $this->assertSame(OnboardingStepStatus::Completed->value, $row->status);
    }

    public function test_get_step_returns_null_when_no_row_exists(): void
    {
        $user = $this->createUser();

        $this->assertNull($this->repository->getStep($user->id, $this->siteId, 'contract'));
    }

    // ── bulkInvalidateCompletedStep ───────────────────────────────────────────

    public function test_bulk_invalidate_transitions_all_completed_rows_for_step(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();
        $userC = $this->createUser();

        foreach ([$userA, $userB] as $user) {
            ContributorOnboardingStep::create([
                'user_id' => $user->id,
                'site_id' => $this->siteId,
                'step'    => 'contract',
                'status'  => OnboardingStepStatus::Completed->value,
            ]);
        }

        // UserC has a pending row — must not be affected.
        ContributorOnboardingStep::create([
            'user_id' => $userC->id,
            'site_id' => $this->siteId,
            'step'    => 'contract',
            'status'  => OnboardingStepStatus::Pending->value,
        ]);

        $count = $this->repository->bulkInvalidateCompletedStep($this->siteId, 'contract');

        $this->assertSame(2, $count);

        foreach ([$userA, $userB] as $user) {
            $this->assertDatabaseHas('oc_contributor_onboarding_steps', [
                'user_id' => $user->id,
                'step'    => 'contract',
                'status'  => OnboardingStepStatus::Invalidated->value,
            ]);
        }

        // Pending row untouched.
        $this->assertDatabaseHas('oc_contributor_onboarding_steps', [
            'user_id' => $userC->id,
            'status'  => OnboardingStepStatus::Pending->value,
        ]);
    }

    public function test_bulk_invalidate_is_scoped_to_step_name(): void
    {
        $user = $this->createUser();

        ContributorOnboardingStep::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'step'    => 'contract',
            'status'  => OnboardingStepStatus::Completed->value,
        ]);

        ContributorOnboardingStep::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'step'    => 'guidelines',
            'status'  => OnboardingStepStatus::Completed->value,
        ]);

        $this->repository->bulkInvalidateCompletedStep($this->siteId, 'contract');

        // Guidelines row must be unaffected.
        $this->assertDatabaseHas('oc_contributor_onboarding_steps', [
            'step'   => 'guidelines',
            'status' => OnboardingStepStatus::Completed->value,
        ]);
    }

    public function test_bulk_invalidate_is_scoped_to_site(): void
    {
        $user      = $this->createUser();
        $otherSite = $this->createSite();

        ContributorOnboardingStep::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'step'    => 'contract',
            'status'  => OnboardingStepStatus::Completed->value,
        ]);

        ContributorOnboardingStep::create([
            'user_id' => $user->id,
            'site_id' => $otherSite->id,
            'step'    => 'contract',
            'status'  => OnboardingStepStatus::Completed->value,
        ]);

        $this->repository->bulkInvalidateCompletedStep($this->siteId, 'contract');

        // Other-site row must remain completed.
        $this->assertDatabaseHas('oc_contributor_onboarding_steps', [
            'site_id' => $otherSite->id,
            'status'  => OnboardingStepStatus::Completed->value,
        ]);
    }

    public function test_bulk_invalidate_returns_zero_when_no_completed_rows_exist(): void
    {
        $count = $this->repository->bulkInvalidateCompletedStep($this->siteId, 'contract');

        $this->assertSame(0, $count);
    }

    // ── Row uniqueness ────────────────────────────────────────────────────────

    public function test_each_user_site_step_combination_has_at_most_one_row(): void
    {
        $user = $this->createUser();

        $this->repository->markCompleted($user->id, $this->siteId, 'profile');
        $this->repository->markPending($user->id, $this->siteId, 'profile');
        $this->repository->markCompleted($user->id, $this->siteId, 'profile');

        $count = ContributorOnboardingStep::where('user_id', $user->id)
            ->where('site_id', $this->siteId)
            ->where('step', 'profile')
            ->count();

        $this->assertSame(1, $count);
    }

    // ── Site isolation ────────────────────────────────────────────────────────

    public function test_steps_are_scoped_to_site(): void
    {
        $user      = $this->createUser();
        $otherSite = $this->createSite();

        $this->repository->markCompleted($user->id, $this->siteId, 'profile');
        $this->repository->markPending($user->id, $otherSite->id, 'profile');

        $this->assertTrue($this->repository->isCompleted($user->id, $this->siteId, 'profile'));
        $this->assertFalse($this->repository->isCompleted($user->id, $otherSite->id, 'profile'));
    }
}