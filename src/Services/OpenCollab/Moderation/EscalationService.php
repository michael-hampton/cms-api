<?php

namespace App\Services\OpenCollab\Moderation;

use App\Enums\OpenCollab\EscalationStatus;
use App\Enums\OpenCollab\ModerationActionType;
use App\Enums\OpenCollab\ModerationQueueStatus;
use App\Enums\OpenCollab\RiskSeverity;
use App\Events\OpenCollab\ModerationEscalationCreatedEvent;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Models\ModerationEscalation;
use App\Repositories\OpenCollab\ModerationEscalationRepository;
use App\Repositories\OpenCollab\ModerationQueueRepository;
use DateTimeImmutable;

class EscalationService
{
    public function __construct(
        private readonly ModerationEscalationRepository $escalationRepository,
        private readonly ModerationQueueRepository $queueRepository,
        private readonly EscalationRoutingService $routingService,
        private readonly EscalationSlaService $slaService,
        private readonly ModerationAuditService $auditService,
        private readonly EventDispatcher $eventDispatcher,
        private readonly Database $database,
    ) {
    }

    public function escalate(
        int $queueEntryId,
        \App\Enums\OpenCollab\EscalationCategory $category,
        RiskSeverity $severity,
        int $createdByUserId,
        ?int $riskMarkerId = null,
        ?int $cmsImageId = null,
    ): ModerationEscalation {
        $entry = $this->queueRepository->find($queueEntryId);

        if ($entry === null) {
            throw new \InvalidArgumentException("Queue entry [{$queueEntryId}] not found.");
        }

        $team = $this->routingService->teamFor($category);
        $dueAt = $this->slaService->dueAt($category, new DateTimeImmutable());

        $escalation = $this->database->transaction(function () use (
            $entry, $category, $severity, $team, $dueAt, $createdByUserId, $riskMarkerId, $cmsImageId
        ): ModerationEscalation {
            $escalation = $this->escalationRepository->create([
                'site_id' => $entry->site_id,
                'queue_entry_id' => $entry->id,
                'page_id' => $entry->page_id,
                'page_version_id' => $entry->page_version_id,
                'cms_image_id' => $cmsImageId,
                'risk_marker_id' => $riskMarkerId,
                'category' => $category->value,
                'severity' => $severity->value,
                'assigned_team' => $team,
                'status' => EscalationStatus::Open->value,
                'due_at' => $dueAt->format('Y-m-d H:i:s'),
                'created_by_user_id' => $createdByUserId,
            ]);

            $this->queueRepository->update($entry->id, [
                'status' => ModerationQueueStatus::Escalated->value,
            ]);

            $this->auditService->record(
                siteId: $entry->site_id,
                pageId: $entry->page_id,
                actorUserId: $createdByUserId,
                action: ModerationActionType::Escalated,
                queueEntryId: $entry->id,
                metadata: ['escalation_id' => $escalation->id, 'category' => $category->value, 'team' => $team],
            );

            return $escalation;
        });

        $this->eventDispatcher->dispatch(new ModerationEscalationCreatedEvent($escalation));

        return $escalation;
    }

    public function assign(int $escalationId, int $userId, int $siteId): ModerationEscalation
    {
        $escalation = $this->findOrFail($escalationId, $siteId);

        return $this->escalationRepository->update($escalation->id, ['assigned_user_id' => $userId]);
    }

    public function acknowledge(int $escalationId, int $userId, int $siteId): ModerationEscalation
    {
        $escalation = $this->findOrFail($escalationId, $siteId);

        return $this->escalationRepository->update($escalation->id, [
            'status' => EscalationStatus::Acknowledged->value,
            'acknowledged_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Resolving may leave the queue entry escalated if other open
     * escalations remain — recalculation of queue status back to
     * "queued" happens only when no open escalations remain.
     */
    public function resolve(int $escalationId, int $userId, int $siteId, string $resolution, ?string $notes): ModerationEscalation
    {
        $escalation = $this->findOrFail($escalationId, $siteId);

        return $this->database->transaction(function () use ($escalation, $userId, $resolution, $notes) {
            $escalation = $this->escalationRepository->update($escalation->id, [
                'status' => EscalationStatus::Resolved->value,
                'resolved_at' => date('Y-m-d H:i:s'),
                'resolution' => $resolution,
                'resolution_notes' => $notes,
            ]);

            $remainingOpen = $this->escalationRepository->openForPage($escalation->site_id, $escalation->page_id);

            if ($remainingOpen->isEmpty()) {
                $queueEntry = $this->queueRepository->find($escalation->queue_entry_id);
                if ($queueEntry !== null && $queueEntry->status === ModerationQueueStatus::Escalated) {
                    $this->queueRepository->update($queueEntry->id, [
                        'status' => ModerationQueueStatus::InReview->value,
                    ]);
                }
            }

            return $escalation;
        });
    }

    private function findOrFail(int $escalationId, int $siteId): ModerationEscalation
    {
        $escalation = $this->escalationRepository->find($escalationId);

        if ($escalation === null || (int)$escalation->site_id !== $siteId) {
            throw new \InvalidArgumentException("Escalation [{$escalationId}] not found for this site.");
        }

        return $escalation;
    }
}