<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repositories\Members;

use App\Models\MemberMerge;
use App\Repositories\Members\MemberMergeRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class MemberMergeRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private MemberMergeRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MemberMergeRepository();
    }

    // ─── recordMerge ─────────────────────────────────────────────────────────

    public function test_record_merge_persists_all_fields(): void
    {
        $primary = $this->createMember();
        $merged  = $this->createMember();
        $admin   = $this->createMember();
        $mergedAt = date('Y-m-d H:i:s');

        $record = $this->repository->recordMerge(
            primaryMemberId: $primary->id,
            mergedMemberId:  $merged->id,
            mergedBy:        $admin->id,
            mergedAt:        $mergedAt,
            reason:          'Duplicate email accounts',
            metadata:        ['match_type' => 'email', 'confidence' => 95],
        );

        $this->assertNotNull($record->id);
        $this->assertEquals($primary->id, $record->primary_member_id);
        $this->assertEquals($merged->id,  $record->merged_member_id);
        $this->assertEquals($admin->id,   $record->merged_by);
        $this->assertEquals('Duplicate email accounts', $record->reason);
    }

    public function test_record_merge_allows_null_reason_and_metadata(): void
    {
        $primary = $this->createMember();
        $merged  = $this->createMember();
        $admin   = $this->createMember();

        $record = $this->repository->recordMerge(
            primaryMemberId: $primary->id,
            mergedMemberId:  $merged->id,
            mergedBy:        $admin->id,
            mergedAt:        date('Y-m-d H:i:s'),
        );

        $this->assertNull($record->reason);
        $this->assertNull($record->metadata);
    }

    public function test_record_merge_persists_json_metadata(): void
    {
        $primary = $this->createMember();
        $merged  = $this->createMember();
        $admin   = $this->createMember();

        $metadata = ['match_type' => 'stripe_customer_id', 'confidence' => 95];

        $record = $this->repository->recordMerge(
            primaryMemberId: $primary->id,
            mergedMemberId:  $merged->id,
            mergedBy:        $admin->id,
            mergedAt:        date('Y-m-d H:i:s'),
            metadata:        $metadata,
        );

        $fresh = MemberMerge::find($record->id);
        $this->assertNotNull($fresh);
        // metadata is stored as JSON; verify round-trip
        $this->assertStringContainsString('stripe_customer_id', $fresh->metadata);
    }

    // ─── findByPrimaryMember ─────────────────────────────────────────────────

    public function test_find_by_primary_member_returns_records_for_that_member(): void
    {
        $primary = $this->createMember();
        $other   = $this->createMember();
        $admin   = $this->createMember();

        $this->repository->recordMerge($primary->id, $this->createMember()->id, $admin->id, date('Y-m-d H:i:s'));
        $this->repository->recordMerge($primary->id, $this->createMember()->id, $admin->id, date('Y-m-d H:i:s'));
        // Different primary — must not appear
        $this->repository->recordMerge($other->id, $this->createMember()->id, $admin->id, date('Y-m-d H:i:s'));

        $results = $this->repository->findByPrimaryMember($primary->id);

        $this->assertCount(2, $results);
        foreach ($results as $r) {
            $this->assertEquals($primary->id, $r->primary_member_id);
        }
    }

    public function test_find_by_primary_member_returns_empty_when_none(): void
    {
        $member = $this->createMember();

        $results = $this->repository->findByPrimaryMember($member->id);

        $this->assertCount(0, $results);
    }

    // ─── findByMergedMember ──────────────────────────────────────────────────

    public function test_find_by_merged_member_returns_records_for_that_member(): void
    {
        $merged  = $this->createMember();
        $primary = $this->createMember();
        $admin   = $this->createMember();

        $this->repository->recordMerge($primary->id, $merged->id, $admin->id, date('Y-m-d H:i:s'));

        $results = $this->repository->findByMergedMember($merged->id);

        $this->assertCount(1, $results);
        $this->assertEquals($merged->id, $results->first()->merged_member_id);
    }

    public function test_find_by_merged_member_returns_empty_when_none(): void
    {
        $member = $this->createMember();

        $results = $this->repository->findByMergedMember($member->id);

        $this->assertCount(0, $results);
    }

    // ─── mergeExistsForPair ──────────────────────────────────────────────────

    public function test_merge_exists_returns_true_when_record_exists_in_primary_direction(): void
    {
        $a     = $this->createMember();
        $b     = $this->createMember();
        $admin = $this->createMember();

        $this->repository->recordMerge($a->id, $b->id, $admin->id, date('Y-m-d H:i:s'));

        $this->assertTrue($this->repository->mergeExistsForPair($a->id, $b->id));
    }

    public function test_merge_exists_returns_true_when_record_exists_in_reverse_direction(): void
    {
        $a     = $this->createMember();
        $b     = $this->createMember();
        $admin = $this->createMember();

        // Stored as a→b; query as b→a — should still find it.
        $this->repository->recordMerge($a->id, $b->id, $admin->id, date('Y-m-d H:i:s'));

        $this->assertTrue($this->repository->mergeExistsForPair($b->id, $a->id));
    }

    public function test_merge_exists_returns_false_when_no_record_exists(): void
    {
        $a = $this->createMember();
        $b = $this->createMember();

        $this->assertFalse($this->repository->mergeExistsForPair($a->id, $b->id));
    }

    public function test_merge_exists_does_not_match_unrelated_pair(): void
    {
        $a     = $this->createMember();
        $b     = $this->createMember();
        $c     = $this->createMember();
        $admin = $this->createMember();

        $this->repository->recordMerge($a->id, $b->id, $admin->id, date('Y-m-d H:i:s'));

        // a↔c — no record
        $this->assertFalse($this->repository->mergeExistsForPair($a->id, $c->id));
    }
}