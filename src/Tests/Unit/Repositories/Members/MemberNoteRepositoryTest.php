<?php

namespace App\Tests\Unit\Repositories\Members;

namespace App\Tests\Unit\Repositories\Members;

use App\Models\MemberNote;
use App\Models\Model;
use App\Repositories\Members\MemberNoteRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class MemberNoteRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private MemberNoteRepository $repository;

    public function test_get_paginated_returns_only_top_level_notes(): void
    {
        $member = $this->createMember();
        $parent = $this->createNote($member->id);
        $this->createNote($member->id, ['parent_id' => $parent->id]); // reply — must not appear

        $result = $this->repository->getPaginatedForMember($member->id, $this->siteId, 1, 20);

        $this->assertEquals(1, $result['total']);
        $this->assertCount(1, $result['data']);
        $this->assertNull($result['data']->first()->parent_id);
    }

    private function createNote(int $memberId, array $overrides = []): Model
    {
        return MemberNote::create(array_merge([
            'member_id' => $memberId,
            'site_id' => $this->siteId,
            'author_id' => 1,
            'author_name' => 'Admin',
            'body' => 'Test note body',
            'parent_id' => null,
        ], $overrides));
    }

    // getPaginatedForMember ────────────────────────────────────────────────────

    public function test_get_paginated_eager_loads_replies(): void
    {
        $member = $this->createMember();
        $parent = $this->createNote($member->id);
        $this->createNote($member->id, ['parent_id' => $parent->id]);

        $result = $this->repository->getPaginatedForMember($member->id, $this->siteId, 1, 20);

        $note = $result['data']->first();
        $this->assertTrue($note->relationLoaded('replies'));
        $this->assertCount(1, $note->replies);
    }

    public function test_get_paginated_orders_newest_first(): void
    {
        $member = $this->createMember();
        $old = $this->createNote($member->id, ['created_at' => '2024-01-01 10:00:00']);
        $new = $this->createNote($member->id, ['created_at' => '2024-06-01 10:00:00']);

        $result = $this->repository->getPaginatedForMember($member->id, $this->siteId, 1, 20);

        $this->assertEquals($new->id, $result['data']->first()->id);
    }

    public function test_get_paginated_scopes_to_member_and_site(): void
    {
        $member1 = $this->createMember();
        $member2 = $this->createMember();

        $this->createNote($member1->id);
        $this->createNote($member2->id);

        $result = $this->repository->getPaginatedForMember($member1->id, $this->siteId, 1, 20);

        $this->assertEquals(1, $result['total']);
        $this->assertEquals($member1->id, $result['data']->first()->member_id);
    }

    public function test_get_paginated_respects_per_page_and_calculates_last_page(): void
    {
        $member = $this->createMember();
        for ($i = 0; $i < 5; $i++) {
            $this->createNote($member->id);
        }

        $result = $this->repository->getPaginatedForMember($member->id, $this->siteId, 1, 3);

        $this->assertEquals(5, $result['total']);
        $this->assertEquals(2, $result['last_page']);
        $this->assertCount(3, $result['data']);
    }

    public function test_get_paginated_last_page_never_below_one_when_empty(): void
    {
        $member = $this->createMember();

        $result = $this->repository->getPaginatedForMember($member->id, $this->siteId, 1, 20);

        $this->assertEquals(1, $result['last_page']);
        $this->assertEquals(0, $result['total']);
    }

    public function test_get_paginated_returns_correct_meta_fields(): void
    {
        $member = $this->createMember();
        $this->createNote($member->id);

        $result = $this->repository->getPaginatedForMember($member->id, $this->siteId, 1, 20);

        $this->assertEquals(20, $result['per_page']);
        $this->assertEquals(1, $result['current_page']);
        $this->assertEquals(1, $result['last_page']);
    }

    public function test_find_for_member_returns_matching_note(): void
    {
        $member = $this->createMember();
        $note = $this->createNote($member->id);

        $found = $this->repository->findForMember($note->id, $member->id, $this->siteId);

        $this->assertNotNull($found);
        $this->assertEquals($note->id, $found->id);
    }

    // findForMember ───────────────────────────────────────────────────────────

    public function test_find_for_member_returns_null_for_wrong_member(): void
    {
        $m1 = $this->createMember();
        $m2 = $this->createMember();
        $note = $this->createNote($m1->id);

        $found = $this->repository->findForMember($note->id, $m2->id, $this->siteId);

        $this->assertNull($found);
    }

    public function test_find_for_member_returns_null_when_not_found(): void
    {
        $member = $this->createMember();

        $found = $this->repository->findForMember(99999, $member->id, $this->siteId);

        $this->assertNull($found);
    }

    public function test_delete_parent_removes_note_and_all_replies(): void
    {
        $member = $this->createMember();
        $parent = $this->createNote($member->id);
        $reply1 = $this->createNote($member->id, ['parent_id' => $parent->id]);
        $reply2 = $this->createNote($member->id, ['parent_id' => $parent->id]);

        $this->repository->deleteParentAndChildren($parent);

        $this->assertNull(MemberNote::find($parent->id));
        $this->assertNull(MemberNote::find($reply1->id));
        $this->assertNull(MemberNote::find($reply2->id));
    }

    // deleteParentAndChildren ─────────────────────────────────────────────────

    public function test_delete_parent_does_not_affect_other_members_notes(): void
    {
        $m1 = $this->createMember();
        $m2 = $this->createMember();
        $target = $this->createNote($m1->id);
        $other = $this->createNote($m2->id);

        $this->repository->deleteParentAndChildren($target);

        $this->assertNotNull(MemberNote::find($other->id));
    }

    public function test_delete_parent_without_replies_succeeds(): void
    {
        $member = $this->createMember();
        $note = $this->createNote($member->id);

        $this->repository->deleteParentAndChildren($note);

        $this->assertNull(MemberNote::find($note->id));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MemberNoteRepository();
    }
}