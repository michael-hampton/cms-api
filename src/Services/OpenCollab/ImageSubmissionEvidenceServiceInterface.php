<?php

namespace App\Services\OpenCollab;

use App\DTO\OpenCollab\ImageEvidenceData;
use App\Models\ImageSubmissionEvidence;

interface ImageSubmissionEvidenceServiceInterface
{
    /**
     * Record evidence of an OpenCollab image upload.
     * Must only be called after the CMS image has been successfully created.
     *
     * When a correlation ID is provided and a record with that ID already exists,
     * the existing record is returned (idempotent retry).
     */
    public function record(ImageEvidenceData $data): ImageSubmissionEvidence;

    /**
     * Check whether a successful upload evidence record exists for this image/contributor pair.
     */
    public function hasEvidence(int $cmsImageId, int $contributorId): bool;
}