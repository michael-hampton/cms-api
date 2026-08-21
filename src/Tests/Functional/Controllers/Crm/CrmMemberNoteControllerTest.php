<?php

namespace App\Tests\Functional\Controllers\Crm;

use App\Models\Member;
use App\Models\MemberNote;
use App\Models\Model;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

/**
 * Functional tests for CrmMemberNoteController.
 *
 * Routes under test (all scoped to /crm/members/{memberId}/notes):
 *   GET    index   — list notes for a member
 *   POST   store   — create a note or reply
 *   POST   update  — edit an existing note
 *   DELETE destroy — delete a note
 *
 * Response shapes:
 *   index   → resourceResponse  → { success: true, items: [...], pagination: {...} }
 *   store   → resourceResponse  → { success: true, message: '...', note: {...} }  (201)
 *   update  → jsonResponse      → { data: { success: true, message: '...', note: {...} }, status: 200, ... }
 *   destroy → jsonResponse      → { data: { success: true, message: '...' }, status: 200, ... }
 *   errors  → jsonResponse      → { data: { success: false, ... }, status: 4xx, ... }
 *             or errorResponse  → { error: '...', success: false, status: 4xx, ... }
 */
class CrmMemberNoteControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;

    // ── index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_200_with_notes_for_member(): void
    {
        $this->createNote(['member_id' => $this->member->id, 'body' => 'First note']);
        $this->createNote(['member_id' => $this->member->id, 'body' => 'Second note']);

        $response = $this->getForSite('/api/crm/members/' . $this->member->id . '/notes');

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(2, $data['items']);
    }

    private function createNote(array $overrides = []): Model
    {
        return MemberNote::create(array_merge([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'body' => 'Default note body',
            'parent_id' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    public function test_index_returns_empty_items_when_member_has_no_notes(): void
    {
        $response = $this->getForSite('/api/crm/members/' . $this->member->id . '/notes');

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertCount(0, $data['items']);
    }

    public function test_index_returns_401_for_unauthenticated_request(): void
    {
        $this->unauthenticate();

        $response = $this->getForSite('/api/crm/members/' . $this->member->id . '/notes');

        $this->assertResponseStatus(401, $response);
    }

    public function test_index_returns_404_for_non_existent_member(): void
    {
        $response = $this->getForSite('/api/crm/members/999999/notes');

        $this->assertResponseStatus(404, $response);
    }

    public function test_index_paginates_notes(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->createNote(['member_id' => $this->member->id, 'body' => "Note {$i}"]);
        }

        $response = $this->getForSite('/api/crm/members/' . $this->member->id . '/notes?per_page=10&page=1');

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertCount(10, $data['items']);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertEquals(25, $data['pagination']['total']);
    }

    public function test_index_only_returns_notes_for_requested_member(): void
    {
        $otherMember = $this->createMember();
        $this->createNote(['member_id' => $this->member->id, 'body' => 'My note']);
        $this->createNote(['member_id' => $otherMember->id, 'body' => 'Other note']);

        $response = $this->getForSite('/api/crm/members/' . $this->member->id . '/notes');

        $data = json_decode($response->getContent(), true);
        $bodies = array_column($data['items'], 'body');

        $this->assertContains('My note', $bodies);
        $this->assertNotContains('Other note', $bodies);
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_index_response_includes_pagination_metadata(): void
    {
        $this->createNote(['member_id' => $this->member->id]);

        $response = $this->getForSite('/api/crm/members/' . $this->member->id . '/notes');

        $data = json_decode($response->getContent(), true);
        $pagination = $data['pagination'];

        $this->assertArrayHasKey('total', $pagination);
        $this->assertArrayHasKey('per_page', $pagination);
        $this->assertArrayHasKey('current_page', $pagination);
    }

    public function test_index_filters_by_created_at_range(): void
    {
        $inRange = $this->createNote(['member_id' => $this->member->id, 'body' => 'In Range Note']);
        $outOfRange = $this->createNote(['member_id' => $this->member->id, 'body' => 'Out Of Range Note']);

        MemberNote::where('id', $inRange->id)->update(['created_at' => '2026-03-15 10:00:00']);
        MemberNote::where('id', $outOfRange->id)->update(['created_at' => '2026-01-01 10:00:00']);

        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/notes?date_from=2026-03-01&date_to=2026-03-31'
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $bodies = array_column($data['items'], 'body');
        $this->assertContains('In Range Note', $bodies);
        $this->assertNotContains('Out Of Range Note', $bodies);
    }

    public function test_index_filters_by_updated_at_range(): void
    {
        $inRange = $this->createNote(['member_id' => $this->member->id, 'body' => 'Recently Updated Note']);
        $outOfRange = $this->createNote(['member_id' => $this->member->id, 'body' => 'Stale Note']);

        MemberNote::where('id', $inRange->id)->update(['updated_at' => '2026-03-15 10:00:00']);
        MemberNote::where('id', $outOfRange->id)->update(['updated_at' => '2026-01-01 10:00:00']);

        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/notes?updated_from=2026-03-01&updated_to=2026-03-31'
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $bodies = array_column($data['items'], 'body');
        $this->assertContains('Recently Updated Note', $bodies);
        $this->assertNotContains('Stale Note', $bodies);
    }

    public function test_store_creates_note_and_returns_201(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/notes',
            ['body' => 'A brand new note']
        );

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('note', $data);
        $this->assertEquals('Note saved.', $data['message']);
    }

    public function test_store_persists_note_to_database(): void
    {
        $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/notes',
            ['body' => 'Persisted note body']
        );

        $this->assertDatabaseHas('member_notes', [
            'member_id' => $this->member->id,
            'body' => 'Persisted note body',
        ]);
    }

    public function test_store_returns_401_for_unauthenticated_request(): void
    {
        $this->unauthenticate();

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/notes',
            ['body' => 'Should not save']
        );

        $this->assertResponseStatus(401, $response);
    }

    public function test_store_returns_422_when_body_is_missing(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/notes',
            []
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function test_store_returns_422_when_body_is_empty_string(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/notes',
            ['body' => '']
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_store_creates_reply_when_parent_id_is_provided(): void
    {
        $parent = $this->createNote(['member_id' => $this->member->id, 'body' => 'Parent note']);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/notes',
            ['body' => 'A reply', 'parent_id' => $parent->id]
        );

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('note', $data);
    }

    public function test_store_reply_is_persisted_with_correct_parent(): void
    {
        $parent = $this->createNote(['member_id' => $this->member->id, 'body' => 'Parent']);

        $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/notes',
            ['body' => 'Reply body', 'parent_id' => $parent->id]
        );

        $this->assertDatabaseHas('member_notes', [
            'member_id' => $this->member->id,
            'parent_id' => $parent->id,
            'body' => 'Reply body',
        ]);
    }

    public function test_store_note_response_includes_replies_array(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/notes',
            ['body' => 'Note with replies key']
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('replies', $data['note']);
        $this->assertIsArray($data['note']['replies']);
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_store_returns_404_for_non_existent_member(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/999999/notes',
            ['body' => 'Orphan note']
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_update_persists_changed_body(): void
    {
        $note = $this->createNote(['member_id' => $this->member->id, 'body' => 'Original body', 'author_id' => $this->authenticatedUser->id]);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/notes/' . $note->id,
            ['body' => 'Updated body']
        );

        $this->assertResponseStatus(200, $response);

        // update uses jsonResponse: { data: {...}, status: 200, success: true, timestamp: ... }
        $envelope = json_decode($response->getContent(), true);
        $this->assertTrue($envelope['success']);

        $inner = $envelope['data'];
        $this->assertTrue($inner['success']);
        $this->assertEquals('Note updated.', $inner['message']);
        $this->assertEquals('Updated body', $inner['note']['body']);
    }

    public function test_update_saves_new_body_in_database(): void
    {
        $note = $this->createNote(['member_id' => $this->member->id, 'body' => 'Old body', 'author_id' => $this->authenticatedUser->id]);

        $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/notes/' . $note->id,
            ['body' => 'New persisted body']
        );

        $this->assertDatabaseHas('member_notes', [
            'id' => $note->id,
            'body' => 'New persisted body',
        ]);
    }

    public function test_update_returns_401_for_unauthenticated_request(): void
    {
        $note = $this->createNote(['member_id' => $this->member->id]);
        $this->unauthenticate();

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/notes/' . $note->id,
            ['body' => 'Hacked body']
        );

        $this->assertResponseStatus(401, $response);
    }

    public function test_update_returns_422_when_body_is_missing(): void
    {
        $note = $this->createNote(['member_id' => $this->member->id]);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/notes/' . $note->id,
            []
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_update_returns_422_when_note_belongs_to_different_member(): void
    {
        $otherMember = $this->createMember();
        $note = $this->createNote(['member_id' => $otherMember->id, 'body' => 'Not yours']);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/notes/' . $note->id,
            ['body' => 'Trying to update']
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_update_response_includes_replies_array(): void
    {
        $note = $this->createNote(['member_id' => $this->member->id, 'body' => 'Has replies key', 'author_id' => $this->authenticatedUser->id]);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/notes/' . $note->id,
            ['body' => 'Updated']
        );

        $envelope = json_decode($response->getContent(), true);
        $inner = $envelope['data'];
        $this->assertArrayHasKey('replies', $inner['note']);
        $this->assertIsArray($inner['note']['replies']);
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_update_returns_422_for_non_existent_note(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/notes/999999',
            ['body' => 'Ghost update']
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_destroy_deletes_note_belonging_to_member(): void
    {
        $note = $this->createNote(['member_id' => $this->member->id, 'body' => 'Delete me', 'author_id' => $this->authenticatedUser->id]);

        $response = $this->deleteForSite(
            '/api/crm/members/' . $this->member->id . '/notes/' . $note->id
        );

        $this->assertResponseStatus(200, $response);

        // destroy uses jsonResponse wrapper
        $envelope = json_decode($response->getContent(), true);
        $this->assertTrue($envelope['success']);

        $inner = $envelope['data'];
        $this->assertTrue($inner['success']);
        $this->assertEquals('Note deleted.', $inner['message']);
    }

    public function test_destroy_removes_note_from_database(): void
    {
        $note = $this->createNote(['member_id' => $this->member->id, 'author_id' => $this->authenticatedUser->id]);

        $this->deleteForSite(
            '/api/crm/members/' . $this->member->id . '/notes/' . $note->id
        );

        $this->assertSoftDeleted('member_notes', [
            'id' => $note->id,
        ]);
    }

    public function test_destroy_returns_401_for_unauthenticated_request(): void
    {
        $note = $this->createNote(['member_id' => $this->member->id]);
        $this->unauthenticate();

        $response = $this->deleteForSite(
            '/api/crm/members/' . $this->member->id . '/notes/' . $note->id
        );

        $this->assertResponseStatus(401, $response);
    }

    public function test_destroy_returns_422_when_note_belongs_to_different_member(): void
    {
        $otherMember = $this->createMember();
        $note = $this->createNote(['member_id' => $otherMember->id]);

        $response = $this->deleteForSite(
            '/api/crm/members/' . $this->member->id . '/notes/' . $note->id
        );

        $this->assertResponseStatus(422, $response);
        $this->assertDatabaseHas('member_notes', ['id' => $note->id]);
    }

    public function test_destroy_returns_422_for_non_existent_note(): void
    {
        $response = $this->deleteForSite(
            '/api/crm/members/' . $this->member->id . '/notes/999999'
        );

        $this->assertResponseStatus(422, $response);
    }

    // ── setup / helpers ───────────────────────────────────────────────────────

    public function test_destroy_returns_404_for_non_existent_member(): void
    {
        $response = $this->deleteForSite(
            '/api/crm/members/999999/notes/1'
        );

        $this->assertResponseStatus(422, $response);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = $this->createMember([
            'first_name' => 'Note',
            'last_name' => 'Tester',
            'email' => 'note.tester.' . uniqid() . '@example.com',
            'is_active' => true,
            'anonymous' => false,
            'site_id' => $this->siteId
        ]);
    }
}