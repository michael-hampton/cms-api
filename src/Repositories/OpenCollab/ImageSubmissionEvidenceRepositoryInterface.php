<?php

namespace App\Repositories\OpenCollab;

use App\Models\ImageSubmissionEvidence;

interface ImageSubmissionEvidenceRepositoryInterface
{
    public function create(array $data): ImageSubmissionEvidence;

    public function findByCmsImageAndContributor(int $cmsImageId, int $contributorId): ?ImageSubmissionEvidence;

    public function findByCorrelationId(string $correlationId): ?ImageSubmissionEvidence;

    /** @return \App\Framework\Support\Collection<ImageSubmissionEvidence> */
    public function findByCmsImageId(int $cmsImageId): \App\Framework\Support\Collection;
}