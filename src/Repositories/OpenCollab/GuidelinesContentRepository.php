<?php

namespace App\Repositories\OpenCollab;

use App\Models\Guideline;
use App\Models\Model;
use App\Repositories\Repository;

/**
 * Manages versioned guideline content stored in oc_guidelines.
 *
 * Named GuidelinesContentRepository to avoid collision with the existing
 * GuidelinesRepository which manages UserGuidelinesAcknowledgement records.
 *
 * Usage pattern mirrors ContractRepository exactly.
 */
class GuidelinesContentRepository extends Repository
{
    /**
     * A specific version for a site.
     */
    public function findVersion(int $siteId, int $version): ?Guideline
    {
        /** @var Guideline|null */
        return Guideline::where('site_id', $siteId)
            ->where('version', $version)
            ->first();
    }

    /**
     * All guideline versions for a site, newest first.
     */
    public function allForSite(int $siteId): \App\Framework\Support\Collection
    {
        return Guideline::where('site_id', $siteId)
            ->orderByDesc('version')
            ->get();
    }

    /**
     * Creates a new version. Version number is auto-incremented from the
     * current max for the site.
     */
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

    /**
     * The latest (highest version) guidelines for a site.
     */
    public function latestForSite(int $siteId): ?Guideline
    {
        /** @var Guideline|null */
        return Guideline::where('site_id', $siteId)
            ->orderByDesc('version')
            ->first();
    }

    protected function getModelClass(): string
    {
        return Guideline::class;
    }
}