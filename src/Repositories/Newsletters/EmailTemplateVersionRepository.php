<?php

namespace App\Repositories\Newsletters;

use App\Framework\Support\Collection;
use App\Models\EmailTemplateVersion;
use App\Models\Model;
use App\Models\NewsletterLayoutVersion;
use App\Repositories\Repository;

/**
 * Persistence for EmailTemplateVersion records.
 *
 * Mirrors the version-related methods on NewsletterLayoutRepository so both
 * systems share the same patterns. No business logic lives here.
 */
class EmailTemplateVersionRepository extends Repository
{
    /**
     * All versions for a template, newest first.
     */
    public function allForTemplate(int $templateId): Collection
    {
        return NewsletterLayoutVersion::where('layout_id', $templateId)
            ->with(['creator'])
            ->orderBy('version_number', 'desc')
            ->get();
    }

    // ── Read ──────────────────────────────────────────────────────────────────

    /**
     * The highest version_number recorded for a template (0 when none exists).
     */
    public function maxVersionNumber(int $templateId): int
    {
        $latest = NewsletterLayoutVersion::where('layout_id', $templateId)
            ->orderBy('version_number', 'desc')
            ->first();

        return $latest ? $latest->version_number : 0;
    }

    public function createVersion(int $templateId, int $versionNumber, array $snapshot, ?int $createdBy): Model
    {
        return NewsletterLayoutVersion::create([
            'layout_id' => $templateId,
            'version_number' => $versionNumber,
            'layout_definition_json' => $snapshot
        ]);
    }

    protected function getModelClass(): string
    {
        return NewsletterLayoutVersion::class;
    }
}