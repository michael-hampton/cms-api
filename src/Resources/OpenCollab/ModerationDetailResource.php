<?php

namespace App\Resources\OpenCollab;

use App\Enums\OpenCollab\ModerationQueueStatus;
use App\Framework\Authorization\Auth;
use App\Framework\Resource\JsonResource;
use App\Models\ModerationQueueEntry;
use App\Models\Page;
use App\Repositories\OpenCollab\ModerationActionRepository;
use App\Repositories\OpenCollab\ModerationEscalationRepository;
use App\Repositories\OpenCollab\RiskMarkerRepository;
use App\Services\OpenCollab\Moderation\Governance\ContentGovernanceGate;
use App\Services\OpenCollab\OpenCollabAuthorizationService;

/**
 * Full detail resource (Ticket 14). Composes the lighter resources above.
 * Sensitive legal notes (resolution_notes on legal-category escalations)
 * are hidden unless $canViewHighRisk is true.
 */
class ModerationDetailResource extends JsonResource
{

    public function toArray(): array
    {
        $riskMarkerRepository = app(RiskMarkerRepository::class);
        $escalationRepository = app(ModerationEscalationRepository::class);
        $governanceGate = app(ContentGovernanceGate::class);

        $page = $this->resource->page();
        $assignedUser = $this->resource->assignedUser();

        $risks = $riskMarkerRepository->outstandingForPage($this->getAttribute('site_id'), $this->getAttribute('page_id'));
        $escalations = $escalationRepository->openForPage($this->getAttribute('site_id'), $this->getAttribute('page_id'));
        $governance = $governanceGate->check($this->getAttribute('page_id'));
        $authorizationService = app(OpencollabAuthorizationService::class);

        $canViewHighRisk = $authorizationService->canViewHighRisk($this->getAttribute('site_id'), $this->getAttribute('page_id'));
        $canEscalate = $authorizationService->canEscalate($this->getAttribute('site_id'), $this->getAttribute('page_id'));
        $canResolveRisk = $authorizationService->canResolveRisk($this->getAttribute('site_id'), $this->getAttribute('page_id'));

        return [
            'id' => $this->getAttribute('id'),
            'page' => [
                'id' => $page?->id,
                'title' => $page?->title,
                'status' => $page?->status,
            ],
            'contributor' => [
                'id' => $page?->contributor_id,
            ],
            'status' => $this->getAttribute('status'),
            'risk_score' => $this->getAttribute('risk_score'),
            'priority_score' => $this->getAttribute('priority_score'),
            'risk_markers' => array_map(
                fn($m) => $canViewHighRisk || $m->severity !== 'critical'
                    ? (new RiskMarkerResource($m))->toArray()
                    : ['id' => $m->id, 'severity' => $m->severity, 'status' => $m->status],
                $risks->all()
            ),
            'escalations' => array_map(
                fn($e) => $canViewHighRisk
                    ? (new EscalationResource($e))->toArray()
                    : ['id' => $e->id, 'category' => $e->category->value, 'status' => $e->status->value],
                $escalations->all()
            ),
            'governance' => [
                'can_approve' => $governance->passed,
                'blockers' => array_map(
                    fn($f) => ['code' => $f->code, 'message' => $f->message],
                    $governance->failures
                ),
            ],
            'available_actions' => $this->availableActions($canEscalate, $canResolveRisk),
            'assigned_to_user_id' => $this->getAttribute('assigned_to_user_id'),
            'assigned_to_display_name' => $assignedUser?->display_name, // ASSUMED: belongsTo relation + UserRepository exposes display_name
            'last_reviewed_at' => $this->lastReviewedAt(),
            'internal_notes' => $page?->moderation_internal_notes, // ASSUMED column, see below
            'image_rights_summary' => $this->imageRightsSummary($page),
        ];
    }

    private function availableActions(bool $canEscalate, bool $canResolveRisk): array
    {
        // Mirrors ModerationQueueEntryResource's logic but with the fuller
        // action set needed by the detail screen. Computed server-side so
        // the UI never has to encode permission rules.
        $actions = [];

        if ($this->getAttribute('assigned_to_user_id') === null) {
            $actions[] = 'claim';
        } elseif ((int)$this->getAttribute('assigned_to_user_id') === Auth::id()) {
            $actions[] = 'release';
        }

        if (ModerationQueueStatus::tryFrom($this->getAttribute('status'))?->isOpen()) {
            if ($this->viewerCan('approve')) $actions[] = 'approve';
            if ($this->viewerCan('reject')) $actions[] = 'reject';
            if ($this->viewerCan('request_changes')) $actions[] = 'request_changes';
            if ($this->viewerCan('review')) $actions[] = 'add_risk';
            if ($canEscalate) $actions[] = 'escalate';
        }

        $canResolveRisk = true; //todo

        if ($canResolveRisk) $actions[] = 'resolve_risk';

        return $actions;
    }

    private function lastReviewedAt(): ?string
    {
        // ASSUMED: most recent oc_moderation_actions row with action in
        // ['review_started','approved','rejected','changes_requested']
        // for this queue entry. Cheapest correct approach: add a method
        // to ModerationActionRepository::lastReviewActionAt(int $queueEntryId): ?Carbon
        return null; // wire up once that repo method exists
    }

    private function imageRightsSummary(?Page $page): string
    {
        // ASSUMED: depends on your CMS image rights model — placeholder.
        return 'No issues reported';
    }

    private function viewerCan(string $string)
    {
        return true; //todo
    }
}