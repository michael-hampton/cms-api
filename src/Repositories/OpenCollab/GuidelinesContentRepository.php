<?php

namespace App\Repositories\OpenCollab;

use App\Models\Guideline;
use App\Models\Model;
use App\Models\UserGuidelinesAcknowledgement;
use App\Repositories\Repository;

/**
 * Manages versioned guideline content stored in oc_guidelines.
 */
class GuidelinesContentRepository extends Repository
{
    public function findVersion(int $siteId, int $version): ?Guideline
    {
        return Guideline::where('site_id', $siteId)->where('version', $version)->first();
    }

    public function allForSite(int $siteId): \App\Framework\Support\Collection
    {
        return Guideline::where('site_id', $siteId)->orderByDesc('version')->get();
    }

    public function createVersion(int $siteId, string $content): Model
    {
        $latest = $this->latestForSite($siteId);
        $nextVersion = $latest ? $latest->version + 1 : 1;

        return $this->create([
            'site_id' => $siteId,
            'version' => $nextVersion,
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function latestForSite(int $siteId): ?Guideline
    {
        return Guideline::where('site_id', $siteId)->orderByDesc('version')->first();
    }

    /**
     * Returns true if ANY contributor has acknowledged this guidelines version.
     * Used to guard against editing/deleting acknowledged guidelines.
     */
    public function hasAnyAcknowledged(int $guidelineId): bool
    {
        $guideline = $this->find($guidelineId);
        if (!$guideline) {
            return false;
        }

        return UserGuidelinesAcknowledgement::where('site_id', $guideline->site_id)
            ->where('version', $guideline->version)
            ->exists();
    }

    protected function getModelClass(): string
    {
        return Guideline::class;
    }
}