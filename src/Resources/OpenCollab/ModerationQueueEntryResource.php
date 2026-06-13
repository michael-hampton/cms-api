<?php

namespace App\Resources\OpenCollab;

use App\Models\ModerationQueueEntry;
use App\Services\OpenCollab\OpenCollabAuthorizationService;
use App\Models\Site;

/**
 * Lightweight list-view resource (Ticket 14).
 * Permission-aware "available actions" based on the viewing user.
 */
class ModerationQueueEntryResource
{
    public function __construct(
        private readonly ModerationQueueEntry $entry,
        private readonly ?int $viewerUserId = null,
        private readonly ?Site $site = null,
        private readonly ?OpenCollabAuthorizationService $authorization = null,
    ) {
    }

    public function toArray(): array
    {
        $page = $this->entry->page; // ASSUMED: relation defined on ModerationQueueEntry model

        return [
            'id' => $this->entry->id,
            'page' => [
                'id' => $page?->id,
                'title' => $page?->title,
            ],
            'contributor' => [
                'id' => $page?->contributor_id,
            ],
            'status' => $this->entry->status->value,
            'submitted_at' => $this->entry->submitted_at->toIso8601String(),
            'assigned_to_user_id' => $this->entry->assigned_to_user_id,
            'risk_score' => $this->entry->risk_score,
            'priority_score' => $this->entry->priority_score,
            'available_actions' => $this->availableActions(),
        ];
    }

    private function availableActions(): array
    {
        if ($this->viewerUserId === null || $this->site === null || $this->authorization === null) {
            return [];
        }

        $actions = [];

        if ($this->entry->assigned_to_user_id === null) {
            $actions[] = 'claim';
        } elseif ((int)$this->entry->assigned_to_user_id === $this->viewerUserId) {
            $actions[] = 'release';
        }

        if ($this->authorization->canEscalate($this->viewerUserId, $this->site)) {
            $actions[] = 'escalate';
        }

        return $actions;
    }
}