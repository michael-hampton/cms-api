<?php

namespace App\Services\OpenCollab\Moderation;

use App\Enums\OpenCollab\ModerationActionType;
use App\Models\Model;
use App\Models\ModerationAction;
use App\Repositories\OpenCollab\ModerationActionRepository;

class ModerationAuditService
{
    public function __construct(
        private readonly ModerationActionRepository $actionRepository,
    ) {
    }

    public function record(
        int $siteId,
        int $pageId,
        int $actorUserId,
        ModerationActionType $action,
        ?int $queueEntryId = null,
        ?int $pageVersionId = null,
        ?string $reasonCode = null,
        ?string $notes = null,
        array $metadata = [],
    ): Model {
        return $this->actionRepository->create([
            'site_id' => $siteId,
            'queue_entry_id' => $queueEntryId,
            'page_id' => $pageId,
            'page_version_id' => $pageVersionId,
            'actor_user_id' => $actorUserId,
            'action' => $action->value,
            'reason_code' => $reasonCode,
            'notes' => $notes,
            'metadata' => $metadata ?: null,
        ]);
    }
}