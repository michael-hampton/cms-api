<?php

namespace App\Resources\OpenCollab;

use App\Models\ModerationQueueEntry;
use App\Repositories\OpenCollab\ModerationActionRepository;
use App\Repositories\OpenCollab\ModerationEscalationRepository;
use App\Repositories\OpenCollab\RiskMarkerRepository;
use App\Services\OpenCollab\Moderation\Governance\ContentGovernanceGate;

/**
 * Full detail resource (Ticket 14). Composes the lighter resources above.
 * Sensitive legal notes (resolution_notes on legal-category escalations)
 * are hidden unless $canViewHighRisk is true.
 */
class ModerationDetailResource
{
    public function __construct(
        private readonly ModerationQueueEntry $entry,
        private readonly RiskMarkerRepository $riskMarkerRepository,
        private readonly ModerationEscalationRepository $escalationRepository,
        private readonly ContentGovernanceGate $governanceGate,
        private readonly bool $canViewHighRisk = false,
    ) {
    }

    public function toArray(): array
    {
        $page = $this->entry->page;

        $risks = $this->riskMarkerRepository->outstandingForPage($this->entry->site_id, $this->entry->page_id);
        $escalations = $this->escalationRepository->openForPage($this->entry->site_id, $this->entry->page_id);
        $governance = $this->governanceGate->check($this->entry->page_id);

        return [
            'id' => $this->entry->id,
            'page' => [
                'id' => $page?->id,
                'title' => $page?->title,
                'status' => $page?->status,
            ],
            'contributor' => [
                'id' => $page?->contributor_id,
            ],
            'status' => $this->entry->status->value,
            'risk_score' => $this->entry->risk_score,
            'priority_score' => $this->entry->priority_score,
            'risk_markers' => array_map(
                fn($m) => $this->canViewHighRisk || $m->severity->value !== 'critical'
                    ? (new RiskMarkerResource($m))->toArray()
                    : ['id' => $m->id, 'severity' => $m->severity->value, 'status' => $m->status->value],
                $risks->all()
            ),
            'escalations' => array_map(
                fn($e) => $this->canViewHighRisk
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
        ];
    }
}