<?php

namespace App\Resources\OpenCollab;

use App\Framework\Authorization\Auth;
use App\Framework\Resource\JsonResource;
use App\Framework\Support\SiteContext;
use App\Models\ModerationQueueEntry;
use App\Services\Cms\ContentWorkflowAuthorizationService;
use App\Services\OpenCollab\OpenCollabAuthorizationService;
use App\Models\Site;

/**
 * Lightweight list-view resource (Ticket 14).
 * Permission-aware "available actions" based on the viewing user.
 */
class ModerationQueueEntryResource extends JsonResource
{
    public function toArray(): array
    {
        $page = $this->resource->page; // ASSUMED: relation defined on ModerationQueueEntry model

        return [
            'id' => $this->getAttribute('id'),
            'page' => [
                'id' => $page?->id,
                'title' => $page?->title,
            ],
            'contributor' => [
                'id' => $page?->contributor_id,
            ],
            'status' => $this->getAttribute('status'),
            'submitted_at' => $this->getAttribute('submitted_at')?->format('Y-m-d H:i:s'),
            'assigned_to_user_id' => $this->getAttribute('assigned_to_user_id'),
            'risk_score' => $this->getAttribute('risk_score'),
            'priority_score' => $this->getAttribute('priority_score'),
            'available_actions' => $this->availableActions(),
        ];
    }

    private function availableActions(): array
    {
        if (!Auth::user() || !SiteContext::slug()) {
            return [];
        }

        $actions = [];

        if ($this->getAttribute('assigned_to_user_id') === null) {
            $actions[] = 'claim';
        } elseif ((int)$this->getAttribute('assigned_to_user_id') === Auth::id()) {
            $actions[] = 'release';
        }

        $authorizationService = app(ContentWorkflowAuthorizationService::class);

        $authorizationService->assertCanEscalate(Auth::id(), SiteContext::getId());

        $actions[] = 'escalate';

        return $actions;
    }
}