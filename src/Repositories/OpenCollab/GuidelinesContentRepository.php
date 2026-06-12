<?php

namespace App\Repositories\OpenCollab;

use App\Enums\OpenCollab\GuidelineStatus;
use App\Framework\Support\Collection;
use App\Models\Guideline;
use App\Models\Model;
use App\Models\UserGuidelinesAcknowledgement;
use App\Repositories\Repository;

/**
 * Manages versioned guideline content stored in oc_guidelines.
 */
class GuidelinesContentRepository extends Repository
{
    // ── Version Resolution ────────────────────────────────────────────────────

    public function findVersion(int $siteId, int $version): ?Guideline
    {
        return Guideline::where('site_id', $siteId)->where('version', $version)->first();
    }

    public function allForSite(int $siteId): Collection
    {
        return Guideline::where('site_id', $siteId)->orderByDesc('version')->get();
    }

    /**
     * Highest version regardless of status. Used by admin listing.
     */
    public function latestForSite(int $siteId): ?Guideline
    {
        return Guideline::where('site_id', $siteId)->orderByDesc('version')->first();
    }

    /**
     * Latest published version. Used by compliance/onboarding resolution.
     * Drafts and archived versions are intentionally excluded.
     */
    public function latestPublishedForSite(int $siteId): ?Guideline
    {
        return Guideline::where('site_id', $siteId)
            ->where('status', GuidelineStatus::Published->value)
            ->orderByDesc('version')
            ->first();
    }

    // ── Lifecycle Writes ──────────────────────────────────────────────────────

    public function createVersion(int $siteId, string $content, array $metadata = []): Model
    {
        $latest = $this->latestForSite($siteId);
        $nextVersion = $latest ? $latest->version + 1 : 1;

        return $this->create([
            'site_id' => $siteId,
            'title' => $metadata['title'] ?? null,
            'version' => $nextVersion,
            'content' => $content,
            'source_type' => $metadata['source_type'] ?? 'manual',
            'content_format' => $metadata['content_format'] ?? 'html',
            'template_id' => $metadata['template_id'] ?? null,
            'document_id' => $metadata['document_id'] ?? null,
            'source_document_id' => $metadata['source_document_id'] ?? null,
            'extraction_status' => $metadata['extraction_status'] ?? 'not_required',
            'extraction_error' => $metadata['extraction_error'] ?? null,
            'status' => GuidelineStatus::Draft->value,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function publish(Guideline $guideline, int $publishedByUserId): Guideline
    {
        $guideline->update([
            'status' => GuidelineStatus::Published->value,
            'published_at' => date('Y-m-d H:i:s'),
            'published_by' => $publishedByUserId,
            'published_by_user_id' => $publishedByUserId,
        ]);

        return $guideline->fresh();
    }

    public function archive(Guideline $guideline, int $archivedByUserId): Guideline
    {
        $guideline->update([
            'status' => GuidelineStatus::Archived->value,
            'archived_at' => date('Y-m-d H:i:s'),
            'archived_by' => $archivedByUserId,
        ]);

        return $guideline->fresh();
    }

    // ── Version Sequencing ────────────────────────────────────────────────────

    public function nextVersionNumber(int $siteId): int
    {
        $latest = $this->latestForSite($siteId);

        return $latest ? $latest->version + 1 : 1;
    }

    // ── Acknowledgement Guards ────────────────────────────────────────────────

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
