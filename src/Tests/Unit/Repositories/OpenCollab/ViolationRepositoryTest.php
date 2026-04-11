<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Enums\OpenCollab\ViolationAction;
use App\Enums\OpenCollab\ViolationSeverity;
use App\Models\ContributorViolation;
use App\Repositories\OpenCollab\ViolationRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class ViolationRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private ViolationRepository $repository;

    public function test_for_contributor_returns_newest_site_violations(): void
    {
        $user = $this->createUser();

        $older = ContributorViolation::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'type' => 'spam',
            'severity' => 'low',
            'reason' => 'Older violation reason.',
            'action_taken' => ViolationAction::Warning->value,
            'created_by' => $user->id,
        ]);
        $this->database->query('UPDATE oc_contributor_violations SET created_at = ? WHERE id = ?', ['2024-01-01 00:00:00', $older->id]);

        $newer = ContributorViolation::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'type' => 'policy',
            'severity' => 'medium',
            'reason' => 'Newer violation reason.',
            'action_taken' => ViolationAction::Suspension->value,
            'created_by' => $user->id,
        ]);
        $this->database->query('UPDATE oc_contributor_violations SET created_at = ? WHERE id = ?', ['2024-06-01 00:00:00', $newer->id]);

        $results = $this->repository->forContributor($user->id, $this->siteId);

        $this->assertCount(2, $results);
        $this->assertEquals($newer->id, $results->first()->id);
        $this->assertNotEquals($older->id, $results->first()->id);
    }

    public function test_unresolved_queries_and_counts_ignore_resolved_rows(): void
    {
        $user = $this->createUser();

        ContributorViolation::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'type' => 'spam',
            'severity' => ViolationSeverity::Medium->value,
            'reason' => 'Active medium severity issue.',
            'action_taken' => ViolationAction::Warning->value,
            'created_by' => $user->id,
        ]);

        ContributorViolation::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'type' => 'quality',
            'severity' => ViolationSeverity::Medium->value,
            'reason' => 'Resolved medium severity issue.',
            'action_taken' => ViolationAction::Warning->value,
            'created_by' => $user->id,
            'resolved_at' => date('Y-m-d H:i:s'),
        ]);

        $unresolved = $this->repository->unresolvedForContributor($user->id, $this->siteId);
        $count = $this->repository->unresolvedCountBySeverity($user->id, $this->siteId, ViolationSeverity::Medium);

        $this->assertCount(1, $unresolved);
        $this->assertEquals(1, $count);
    }

    public function test_active_ban_and_suspension_flags_reflect_unresolved_actions(): void
    {
        $user = $this->createUser();

        ContributorViolation::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'type' => 'policy',
            'severity' => 'high',
            'reason' => 'Active ban reason.',
            'action_taken' => ViolationAction::Ban->value,
            'created_by' => $user->id,
        ]);

        ContributorViolation::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'type' => 'quality',
            'severity' => 'medium',
            'reason' => 'Resolved suspension reason.',
            'action_taken' => ViolationAction::Suspension->value,
            'created_by' => $user->id,
            'resolved_at' => date('Y-m-d H:i:s'),
        ]);

        $this->assertTrue($this->repository->hasActiveBan($user->id, $this->siteId));
        $this->assertFalse($this->repository->hasActiveSuspension($user->id, $this->siteId));
    }

    public function test_for_site_returns_paginated_results_for_single_site(): void
    {
        $user = $this->createUser();
        $otherSite = $this->createSite();

        ContributorViolation::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'type' => 'spam',
            'severity' => 'low',
            'reason' => 'Current site violation.',
            'action_taken' => ViolationAction::Warning->value,
            'created_by' => $user->id,
        ]);

        ContributorViolation::create([
            'user_id' => $user->id,
            'site_id' => $otherSite->id,
            'type' => 'policy',
            'severity' => 'medium',
            'reason' => 'Other site violation.',
            'action_taken' => ViolationAction::Warning->value,
            'created_by' => $user->id,
        ]);

        $results = $this->repository->forSite($this->siteId, 10);

        $this->assertCount(1, $results['data']->all());
        $this->assertEquals($this->siteId, $results['data']->all()[0]->site_id);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ViolationRepository();
    }
}
