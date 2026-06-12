<?php

namespace App\Services\OpenCollab;

use App\Framework\Support\Collection;
use App\Models\Brief;
use App\Models\Collaborator;
use App\Repositories\OpenCollab\ContributorBriefRepository;

class OpenCollabBriefAccessService
{
    public function __construct(
        private readonly ContributorBriefRepository $briefs,
    )
    {
    }

    public function assignmentsForContributor(int $contributorId, int $siteId): Collection
    {
        return $this->briefs->assignmentsForContributor($contributorId, $siteId);
    }

    public function assignmentForBrief(int $briefId, int $contributorId, int $siteId): ?Collaborator
    {
        return $this->briefs->assignmentForBrief($briefId, $contributorId, $siteId);
    }

    public function findAssignedBrief(int $briefId, int $contributorId, int $siteId): ?Brief
    {
        return $this->briefs->findAssignedBrief($briefId, $contributorId, $siteId);
    }

    public function assertCanAccessBrief(int $briefId, int $contributorId, int $siteId): Brief
    {
        $brief = $this->findAssignedBrief($briefId, $contributorId, $siteId);

        if (!$brief) {
            throw new \RuntimeException('Forbidden');
        }

        return $brief;
    }

    public function assertCanAccessAttachment(int $briefId, int $attachmentId, int $contributorId, int $siteId): void
    {
        $this->assertCanAccessBrief($briefId, $contributorId, $siteId);
        $attachment = $this->briefs->findAttachmentForBrief($briefId, $attachmentId);

        if (!$attachment) {
            throw new \RuntimeException('Attachment not found');
        }
    }
}
