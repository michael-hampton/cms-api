<?php

namespace App\Tests\Unit\Services\Members;

use App\Framework\AuthenticatedUser;
use App\Framework\Authorization\Auth;
use App\Models\Member;
use App\Models\MemberNote;
use App\Repositories\Members\CrmMemberRepository;
use App\Repositories\Members\MemberNoteRepository;
use App\Services\Members\MemberNoteService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class MemberNoteServiceTest extends TestCase
{
    private MemberNoteRepository|MockInterface $noteRepository;
    private CrmMemberRepository|MockInterface $memberRepository;
    private MemberNoteService $service;

    public function test_list_for_member_returns_formatted_pagination(): void
    {
        $note = Mockery::mock(MemberNote::class)->makePartial();
        $note->shouldReceive('toArray')->andReturn(['id' => 1, 'body' => 'Hello']);
        $note->replies = collect();

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 10;

        $this->memberRepository
            ->shouldReceive('findForSite')
            ->with(10, 5)
            ->once()
            ->andReturn($member);

        $this->noteRepository
            ->shouldReceive('getPaginatedForMember')
            ->with(10, 5, 1, 20)
            ->once()
            ->andReturn([
                'data' => collect([$note]),
                'total' => 1,
                'per_page' => 20,
                'current_page' => 1,
                'last_page' => 1,
            ]);

        $result = $this->service->listForMember(10, 5);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertCount(1, $result['items']);
        $this->assertEquals(1, $result['pagination']['total']);
    }

    public function test_list_for_member_throws_when_member_not_on_site(): void
    {
        $this->memberRepository
            ->shouldReceive('findForSite')
            ->with(99, 5)
            ->once()
            ->andReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Member not found.');

        $this->service->listForMember(99, 5);
    }

    // ── listForMember ─────────────────────────────────────────────────────────

    public function test_create_note_persists_and_returns_model(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 10;

        $this->actingAsUser(1);

        $this->memberRepository
            ->shouldReceive('findForSite')->with(10, 5)->once()
            ->andReturn($member);

        $expectedNote = Mockery::mock(MemberNote::class)->makePartial();

        $this->noteRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data) {
                return $data['member_id'] === 10
                    && $data['site_id'] === 5
                    && $data['body'] === 'Hello world'
                    && $data['parent_id'] === null
                    && $data['author_id'] === 1;
            }))
            ->andReturn($expectedNote);

        $result = $this->service->createNote(10, 5, 'Hello world');

        $this->assertSame($expectedNote, $result);
    }

    private function actingAsUser(int $id, bool $isAdmin = true)
    {
        $user = Mockery::mock(AuthenticatedUser::class)->makePartial();
        $user->shouldReceive('isAdmin')->andReturn($isAdmin);
        $user->id = $id;
        $user->role = 'admin';
        Auth::$user = $user;
    }

    // ── createNote ───────────────────────────────────────────────────────────

    public function test_create_note_throws_on_empty_body(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 10;

        $this->memberRepository
            ->shouldReceive('findForSite')->with(10, 5)->once()->andReturn($member);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The note body cannot be empty.');

        $this->service->createNote(10, 5, '   ');
    }

    public function test_create_note_throws_when_body_exceeds_5000_chars(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 10;

        $this->memberRepository
            ->shouldReceive('findForSite')->with(10, 5)->once()->andReturn($member);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The note body may not exceed 5 000 characters.');

        $this->service->createNote(10, 5, str_repeat('a', 5001));
    }

    public function test_create_reply_persists_with_parent_id(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 10;

        $parent = Mockery::mock(MemberNote::class)->makePartial();
        $parent->id = 42;
        $parent->parent_id = null;

        $this->actingAsUser(10);

        $this->memberRepository
            ->shouldReceive('findForSite')->with(10, 5)->once()->andReturn($member);

        $this->noteRepository
            ->shouldReceive('findForMember')->with(42, 10, 5)->once()->andReturn($parent);

        $expectedReply = Mockery::mock(MemberNote::class)->makePartial();

        $this->noteRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($d) => $d['parent_id'] === 42 && $d['body'] === 'A reply'))
            ->andReturn($expectedReply);

        $result = $this->service->createReply(10, 5, 42, 'A reply');

        $this->assertSame($expectedReply, $result);
    }

    // ── createReply ──────────────────────────────────────────────────────────

    public function test_create_reply_throws_when_parent_not_found(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 10;

        $this->memberRepository
            ->shouldReceive('findForSite')->with(10, 5)->once()->andReturn($member);

        $this->noteRepository
            ->shouldReceive('findForMember')->with(99, 10, 5)->once()->andReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parent note not found.');

        $this->service->createReply(10, 5, 99, 'Body');
    }

    public function test_create_reply_throws_when_parent_is_itself_a_reply(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 10;

        $parent = Mockery::mock(MemberNote::class)->makePartial();
        $parent->id = 42;
        $parent->parent_id = 1; // already a reply

        $this->memberRepository
            ->shouldReceive('findForSite')->with(10, 5)->once()->andReturn($member);

        $this->noteRepository
            ->shouldReceive('findForMember')->with(42, 10, 5)->once()->andReturn($parent);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot reply to a reply.');

        $this->service->createReply(10, 5, 42, 'Nested reply');
    }

    public function test_update_note_persists_trimmed_body(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 10;

        $this->actingAsUser(10);

        $note = Mockery::mock(MemberNote::class)->makePartial();
        $note->id = 7;
        $note->author_id = 1;

        $this->memberRepository
            ->shouldReceive('findForSite')->with(10, 5)->once()->andReturn($member);

        $this->noteRepository
            ->shouldReceive('findForMember')->with(7, 10, 5)->once()->andReturn($note);

        $updated = Mockery::mock(MemberNote::class)->makePartial();

        $this->noteRepository
            ->shouldReceive('update')
            ->once()
            ->with(7, ['body' => 'Updated body'])
            ->andReturn($updated);

        $result = $this->service->updateNote(10, 5, 7, '  Updated body  ');

        $this->assertSame($updated, $result);
    }

    // ── updateNote ───────────────────────────────────────────────────────────

    public function test_update_note_throws_when_not_author_and_not_admin(): void
    {
        $this->actingAsUser(10, false);

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 10;

        $note = Mockery::mock(MemberNote::class)->makePartial();
        $note->id = 7;
        $note->author_id = 99; // different author

        $this->memberRepository
            ->shouldReceive('findForSite')->with(10, 5)->once()->andReturn($member);

        $this->noteRepository
            ->shouldReceive('findForMember')->with(7, 10, 5)->once()->andReturn($note);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('You do not have permission to modify this note.');

        $this->service->updateNote(10, 5, 7, 'New body');
    }

    public function test_update_note_throws_when_note_not_found(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 10;

        $this->memberRepository
            ->shouldReceive('findForSite')->with(10, 5)->once()->andReturn($member);

        $this->noteRepository
            ->shouldReceive('findForMember')->with(99, 10, 5)->once()->andReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Note not found.');

        $this->service->updateNote(10, 5, 99, 'Body');
    }

    public function test_delete_note_delegates_to_repository(): void
    {
        $this->actingAsUser(10);

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 10;

        $note = Mockery::mock(MemberNote::class)->makePartial();
        $note->id = 7;
        $note->author_id = 1;

        $this->memberRepository
            ->shouldReceive('findForSite')->with(10, 5)->once()->andReturn($member);

        $this->noteRepository
            ->shouldReceive('findForMember')->with(7, 10, 5)->once()->andReturn($note);

        $this->noteRepository
            ->shouldReceive('deleteParentAndChildren')
            ->once()
            ->with($note);

        // No exception means success; void return
        $this->service->deleteNote(10, 5, 7);

        // Assertion to satisfy the "every test must have at least one assertion" rule
        $this->assertTrue(true);
    }

    // ── deleteNote ───────────────────────────────────────────────────────────

    public function test_delete_note_throws_when_note_not_found(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 10;

        $this->memberRepository
            ->shouldReceive('findForSite')->with(10, 5)->once()->andReturn($member);

        $this->noteRepository
            ->shouldReceive('findForMember')->with(99, 10, 5)->once()->andReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Note not found.');

        $this->service->deleteNote(10, 5, 99);
    }

    public function test_delete_note_throws_when_not_author_and_not_admin(): void
    {
        $this->actingAsUser(10, false);

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 10;

        $note = Mockery::mock(MemberNote::class)->makePartial();
        $note->id = 7;
        $note->author_id = 99;

        $this->memberRepository
            ->shouldReceive('findForSite')->with(10, 5)->once()->andReturn($member);

        $this->noteRepository
            ->shouldReceive('findForMember')->with(7, 10, 5)->once()->andReturn($note);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('You do not have permission to modify this note.');

        $this->service->deleteNote(10, 5, 7);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->noteRepository = Mockery::mock(MemberNoteRepository::class);
        $this->memberRepository = Mockery::mock(CrmMemberRepository::class);

        $this->service = new MemberNoteService(
            $this->noteRepository,
            $this->memberRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}