<?php

namespace App\Services\OpenCollab\Risk;

use App\Enums\OpenCollab\ModerationActionType;
use App\Enums\OpenCollab\RiskSeverity;
use App\Enums\OpenCollab\RiskSource;
use App\Enums\OpenCollab\RiskStatus;
use App\Enums\OpenCollab\RiskType;
use App\Events\OpenCollab\RiskMarkerStatusChangedEvent;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Models\ContentRiskMarker;
use App\Repositories\OpenCollab\RiskMarkerRepository;
use App\Services\OpenCollab\Moderation\ModerationAuditService;

class RiskMarkerService
{
    public function __construct(
        private readonly RiskMarkerRepository $riskMarkerRepository,
        private readonly ModerationAuditService $auditService,
        private readonly EventDispatcher $eventDispatcher,
        private readonly Database $database,
    ) {
    }

    public function create(
        int $siteId,
        ?int $pageId,
        ?int $pageVersionId,
        ?int $cmsImageId,
        RiskType $riskType,
        RiskSource $source,
        RiskSeverity $severity,
        ?array $details = null,
        ?int $createdByUserId = null,
        ?int $queueEntryId = null,
    ): ContentRiskMarker {
        return $this->database->transaction(function () use (
            $siteId, $pageId, $pageVersionId, $cmsImageId, $riskType, $source,
            $severity, $details, $createdByUserId, $queueEntryId
        ): ContentRiskMarker {
            $marker = $this->riskMarkerRepository->create([
                'site_id' => $siteId,
                'page_id' => $pageId,
                'page_version_id' => $pageVersionId,
                'cms_image_id' => $cmsImageId,
                'risk_type' => $riskType->value,
                'source' => $source->value,
                'severity' => $severity->value,
                'status' => RiskStatus::Open->value,
                'details' => $details,
                'created_by_user_id' => $createdByUserId,
            ]);

            if ($createdByUserId !== null) {
                $this->auditService->record(
                    siteId: $siteId,
                    pageId: $pageId ?? 0,
                    actorUserId: $createdByUserId,
                    action: ModerationActionType::RiskAdded,
                    queueEntryId: $queueEntryId,
                    metadata: ['risk_marker_id' => $marker->id, 'risk_type' => $riskType->value],
                );
            }

            return $marker;
        }, function (ContentRiskMarker $marker) use ($createdByUserId) {
            // post-commit: recalculation via event
            $this->eventDispatcher->dispatch(
                new RiskMarkerStatusChangedEvent($marker, $createdByUserId ?? 0)
            );
        });
    }

    /**
     * @throws \InvalidArgumentException if notes are required but missing, or wrong site
     */
    public function resolve(int $markerId, int $siteId, int $actorUserId, ?string $notes): ContentRiskMarker
    {
        $marker = $this->riskMarkerRepository->find($markerId);

        if ($marker === null || (int)$marker->site_id !== $siteId) {
            throw new \InvalidArgumentException("Risk marker [{$markerId}] not found for this site.");
        }

        if (in_array($marker->severity, [RiskSeverity::High, RiskSeverity::Critical], true) && empty($notes)) {
            throw new \InvalidArgumentException('Notes are required when resolving a high or critical risk marker.');
        }

        return $this->transitionTo($marker, RiskStatus::Cleared, $siteId, $actorUserId, $notes, ModerationActionType::RiskResolved);
    }

    public function dismiss(int $markerId, int $siteId, int $actorUserId, ?string $notes): ContentRiskMarker
    {
        $marker = $this->riskMarkerRepository->find($markerId);

        if ($marker === null || (int)$marker->site_id !== $siteId) {
            throw new \InvalidArgumentException("Risk marker [{$markerId}] not found for this site.");
        }

        return $this->transitionTo($marker, RiskStatus::Dismissed, $siteId, $actorUserId, $notes, ModerationActionType::RiskResolved);
    }

    private function transitionTo(
        ContentRiskMarker $marker,
        RiskStatus $newStatus,
        int $siteId,
        int $actorUserId,
        ?string $notes,
        ModerationActionType $auditAction,
    ): ContentRiskMarker {
        $marker = $this->database->transaction(function () use ($marker, $newStatus, $siteId, $actorUserId, $notes, $auditAction) {
            $updated = $this->riskMarkerRepository->update($marker->id, [
                'status' => $newStatus->value,
                'reviewed_by_user_id' => $actorUserId,
                'reviewed_at' => date('Y-m-d H:i:s'),
                'resolved_by_user_id' => $actorUserId,
                'resolved_at' => date('Y-m-d H:i:s'),
                'resolution_notes' => $notes,
            ]);

            $this->auditService->record(
                siteId: $siteId,
                pageId: $marker->page_id ?? 0,
                actorUserId: $actorUserId,
                action: $auditAction,
                notes: $notes,
                metadata: ['risk_marker_id' => $marker->id, 'new_status' => $newStatus->value],
            );

            return $updated;
        });

        $this->eventDispatcher->dispatch(
            new RiskMarkerStatusChangedEvent($marker, $actorUserId)
        );

        return $marker;
    }
}