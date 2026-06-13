<?php

namespace App\Repositories\OpenCollab;

use App\Framework\Support\Collection;
use App\Models\ImageSubmissionEvidence;
use App\Repositories\Repository;

class ImageSubmissionEvidenceRepository extends Repository implements ImageSubmissionEvidenceRepositoryInterface
{
    protected function getModelClass(): string
    {
        return ImageSubmissionEvidence::class;
    }

    public function create(array $data): ImageSubmissionEvidence
    {
        return ImageSubmissionEvidence::create($data);
    }

    public function findByCmsImageAndContributor(int $cmsImageId, int $contributorId): ?ImageSubmissionEvidence
    {
        return ImageSubmissionEvidence::where('cms_image_id', $cmsImageId)
            ->where('contributor_user_id', $contributorId)
            ->first();
    }

    public function findByCorrelationId(string $correlationId): ?ImageSubmissionEvidence
    {
        return ImageSubmissionEvidence::where('request_correlation_id', $correlationId)->first();
    }

    public function findByCmsImageId(int $cmsImageId): Collection
    {
        return ImageSubmissionEvidence::where('cms_image_id', $cmsImageId)
            ->orderByDesc('submitted_at')
            ->get();
    }
}