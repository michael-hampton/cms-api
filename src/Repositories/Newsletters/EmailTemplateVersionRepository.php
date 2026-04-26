<?php

namespace App\Repositories\Newsletters;

use App\Framework\Support\Collection;
use App\Models\EmailTemplateVersion;
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
        return EmailTemplateVersion::where('email_template_id', $templateId)
            ->orderBy('version_number', 'desc')
            ->get();
    }

    // ── Read ──────────────────────────────────────────────────────────────────

    /**
     * Find a specific version by its sequential number within a template.
     */
    public function findByNumber(int $templateId, int $versionNumber): ?EmailTemplateVersion
    {
        return EmailTemplateVersion::where('email_template_id', $templateId)
            ->where('version_number', $versionNumber)
            ->first();
    }

    /**
     * The highest version_number recorded for a template (0 when none exists).
     */
    public function maxVersionNumber(int $templateId): int
    {
        $latest = EmailTemplateVersion::where('email_template_id', $templateId)
            ->orderBy('version_number', 'desc')
            ->first();

        return $latest ? $latest->version_number : 0;
    }

    protected function getModelClass(): string
    {
        return EmailTemplateVersion::class;
    }
}