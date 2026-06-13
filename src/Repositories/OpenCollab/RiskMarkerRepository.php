<?php

namespace App\Repositories\OpenCollab;

use App\Enums\OpenCollab\RiskStatus;
use App\Models\ContentRiskMarker;
use App\Framework\Support\Collection;

class RiskMarkerRepository
{
    public function find(int $id): ?ContentRiskMarker
    {
        return ContentRiskMarker::find($id);
    }

    public function create(array $attributes): ContentRiskMarker
    {
        return ContentRiskMarker::create($attributes);
    }

    public function update(int $id, array $attributes): ContentRiskMarker
    {
        $marker = ContentRiskMarker::find($id);
        $marker->update($attributes);
        return $marker->refresh();
    }

    /**
     * @return Collection<ContentRiskMarker>
     */
    public function outstandingForPage(int $siteId, int $pageId): Collection
    {
        $rows = ContentRiskMarker::query()
            ->where('site_id', $siteId)
            ->where('page_id', $pageId)
            ->whereIn('status', [
                RiskStatus::Open->value,
                RiskStatus::UnderReview->value,
                RiskStatus::Confirmed->value,
                RiskStatus::Escalated->value,
            ])
            ->get();

        return Collection::make($rows->all());
    }

    public function findExisting(int $siteId, int $pageId, string $riskType, string $source): ?ContentRiskMarker
    {
        return ContentRiskMarker::query()
            ->where('site_id', $siteId)
            ->where('page_id', $pageId)
            ->where('risk_type', $riskType)
            ->where('source', $source)
            ->first();
    }

    public function findExistingForPage(
        int $siteId,
        int $pageId,
        string $riskType,
        string $source,
    ): ?ContentRiskMarker {
        return ContentRiskMarker::query()
            ->where('site_id', $siteId)
            ->where('page_id', $pageId)
            ->whereNull('cms_image_id')
            ->where('risk_type', $riskType)
            ->where('source', $source)
            ->first();
    }

    public function findExistingForImage(
        int $siteId,
        int $cmsImageId,
        string $riskType,
        string $source,
    ): ?ContentRiskMarker {
        return ContentRiskMarker::query()
            ->where('site_id', $siteId)
            ->where('cms_image_id', $cmsImageId)
            ->where('risk_type', $riskType)
            ->where('source', $source)
            ->first();
    }
}