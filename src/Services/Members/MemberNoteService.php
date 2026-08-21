<?php

namespace App\Services\Members;

use App\Framework\Authorization\Auth;
use App\Models\MemberNote;
use App\Models\Model;
use App\Repositories\Members\CrmMemberRepository;
use App\Repositories\Members\MemberNoteRepository;
use Exception;

class MemberNoteService
{
    public function __construct(
        private readonly MemberNoteRepository $noteRepository,
        private readonly CrmMemberRepository  $memberRepository
    )
    {
    }

    /**
     * List paginated top-level notes (with replies) for a member.
     */
    public function listForMember(
        int $memberId,
        int $siteId,
        int $page = 1,
        int $perPage = 20,
        ?string $createdFrom = null,
        ?string $createdTo = null,
        ?string $updatedFrom = null,
        ?string $updatedTo = null,
    ): array
    {
        $this->assertMemberBelongsToSite($memberId, $siteId);

        $result = $this->noteRepository->getPaginatedForMember(
            $memberId,
            $siteId,
            $page,
            $perPage,
            $createdFrom,
            $createdTo,
            $updatedFrom,
            $updatedTo,
        );

        return [
            'items' => array_map(
                fn(MemberNote $n) => $this->formatNote($n),
                $result['data']->all(),
            ),
            'pagination' => [
                'total' => $result['total'],
                'per_page' => $result['per_page'],
                'current_page' => $result['current_page'],
                'last_page' => $result['last_page'],
            ],
        ];
    }

    private function assertMemberBelongsToSite(int $memberId, int $siteId): void
    {
        if (!$this->memberRepository->findForSite($memberId, $siteId)) {
            throw new \InvalidArgumentException('Member not found.');
        }
    }

    private function formatNote(MemberNote $note): array
    {
        $data = $note->toArray();

        $data['replies'] = ($note->replies ?? collect())
            ->map(fn(MemberNote $r) => $r->toArray())
            ->values()
            ->all();

        return $data;
    }

    /**
     * Create a top-level note.
     */
    public function createNote(int $memberId, int $siteId, string $body): Model
    {
        $this->assertMemberBelongsToSite($memberId, $siteId);
        $this->assertBody($body);

        return $this->noteRepository->create([
            'member_id' => $memberId,
            'site_id' => $siteId,
            'author_id' => Auth::id(),
            'author_name' => Auth::user()?->name ?? 'Unknown',
            'body' => trim($body),
            'parent_id' => null,
        ]);
    }

    private function assertBody(string $body): void
    {
        if (trim($body) === '') {
            throw new Exception('The note body cannot be empty.');
        }

        if (mb_strlen(trim($body)) > 5000) {
            throw new Exception('The note body may not exceed 5 000 characters.');
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Reply to an existing top-level note.
     * Replies cannot themselves be replied to (single-depth threading).
     */
    public function createReply(int $memberId, int $siteId, int $parentId, string $body): Model
    {
        $this->assertMemberBelongsToSite($memberId, $siteId);
        $this->assertBody($body);

        $parent = $this->noteRepository->findForMember($parentId, $memberId, $siteId);

        if (!$parent) {
            throw new \InvalidArgumentException('Parent note not found.');
        }

        if ($parent->parent_id !== null) {
            throw new \InvalidArgumentException('Cannot reply to a reply.');
        }

        return $this->noteRepository->create([
            'member_id' => $memberId,
            'site_id' => $siteId,
            'author_id' => Auth::id(),
            'author_name' => Auth::user()?->name ?? 'Unknown',
            'body' => trim($body),
            'parent_id' => $parentId,
        ]);
    }

    /**
     * Update the body of a note or reply.
     * Only the original author (or a super-admin) may edit.
     */
    public function updateNote(int $memberId, int $siteId, int $noteId, string $body): Model
    {
        $this->assertMemberBelongsToSite($memberId, $siteId);
        $this->assertBody($body);

        $note = $this->findOrFail($noteId, $memberId, $siteId);
        $this->assertCanMutate($note);

        return $this->noteRepository->update($note->id, ['body' => trim($body)]);
    }

    private function findOrFail(int $noteId, int $memberId, int $siteId): MemberNote
    {
        $note = $this->noteRepository->findForMember($noteId, $memberId, $siteId);

        if (!$note) {
            throw new \InvalidArgumentException('Note not found.');
        }

        return $note;
    }

    /**
     * A user may mutate a note if they authored it or they are an admin.
     * Adjust the role/permission check to match your Auth implementation.
     */
    private function assertCanMutate(MemberNote $note): void
    {
        $userId = Auth::id();

        if ($note->author_id !== $userId && !Auth::user()?->isAdmin()) {
            throw new \InvalidArgumentException('You do not have permission to modify this note.');
        }
    }

    /**
     * Delete a note (soft-delete; replies are cascaded in the repository).
     */
    public function deleteNote(int $memberId, int $siteId, int $noteId): void
    {
        $this->assertMemberBelongsToSite($memberId, $siteId);

        $note = $this->findOrFail($noteId, $memberId, $siteId);
        $this->assertCanMutate($note);

        $this->noteRepository->deleteParentAndChildren($note);
    }
}