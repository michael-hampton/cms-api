<?php

namespace App\Services\Newsletter;

use App\Framework\Authorization\Auth;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Framework\Support\Str;
use App\Models\EmailTemplate;
use App\Models\EmailTheme;
use App\Models\Model;
use App\Models\NewsletterLayout;
use App\Repositories\Newsletters\EmailTemplateRepository;
use App\Repositories\Newsletters\EmailTemplateVersionRepository;
use App\Repositories\Newsletters\EmailThemeRepository;

/**
 * Orchestrates the EmailTemplate lifecycle.
 *
 * Version history is now a first-class concern: every create/update/restore
 * operation records an immutable EmailTemplateVersion, mirroring the pattern
 * used by NewsletterLayoutService + NewsletterLayoutVersion.
 *
 * Services MAY:
 *   - Validate business rules
 *   - Coordinate repositories
 *   - Emit domain events
 *   - Control transaction boundaries
 *
 * Services MUST NOT:
 *   - Format data for presentation
 *   - Build HTML (delegated to EmailTemplateRenderer)
 *   - Access sessions or request globals
 */
class EmailTemplateService
{
    public function __construct(
        private readonly Database                       $db,
        private readonly EmailTemplateRepository        $repository,
        private readonly EmailTemplateVersionRepository $versionRepository,
        private readonly EmailThemeRepository           $themeRepository,
        private readonly EmailTemplateRenderer          $renderer,
        private readonly PreviewDataFactory             $previewDataFactory,
    )
    {
    }

    // ── Read ──────────────────────────────────────────────────────────────────

    public function getAllForSite(int $siteId, ?string $category = null): Collection
    {
        return $this->repository->getAllBySite($siteId, $category);
    }

    public function getById(int $id): ?NewsletterLayout
    {
        return $this->repository->find($id);
    }

    public function getBySlug(string $slug, int $siteId): ?NewsletterLayout
    {
        return $this->repository->findBySlug($slug, $siteId);
    }

    // ── Version history ───────────────────────────────────────────────────────

    /**
     * All versions for a template, newest first.
     */
    public function getVersions(int $templateId): Collection
    {
        return $this->versionRepository->allForTemplate($templateId);
    }

    /**
     * Restore a template to the state captured in a specific version.
     *
     * Restoration is non-destructive: it applies the snapshot as a normal
     * update (which records a new version), so the history remains intact.
     */
    public function restoreVersion(int $templateId, int $versionId): EmailTemplate
    {
        return $this->db->transaction(function () use ($templateId, $versionId) {
            $version = $this->versionRepository->find($versionId);

            if (!$version || $version->email_template_id !== $templateId) {
                throw new \RuntimeException("Version {$versionId} not found for template {$templateId}.");
            }

            // apply() records a new version internally, preserving the audit trail.
            return $this->apply($templateId, $version->toRestorePayload());
        });
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    /**
     * Apply an update payload to an existing template and record a version.
     * All callers must already be inside a transaction.
     */
    private function apply(int $id, array $data): NewsletterLayout
    {
        $template = $this->repository->find($id);

        if (!$template) {
            throw new \RuntimeException("Email template {$id} not found.");
        }

        if (isset($data['blocks'])) {
            $data['layout_definition_json'] = [
                'email_template' => [
                    'blocks' => $this->sanitiseBlocks($data['blocks'] ?? []),
                    'description' => $data['description'],
                    'category' => $data['category']
                ]
            ];
        }

        if (isset($data['name']) && $data['name'] !== $template->name && !isset($data['slug'])) {
            $data['slug'] = $this->ensureUniqueSlug(
                Str::slug($data['name']),
                $template->site_id,
                $id,
            );
        }

        $updated = $this->repository->update($id, $data);

        $this->recordVersion($updated);

        return $updated;
    }

    /**
     * Sanitise the blocks array — enforce the minimum required shape so the
     * renderer never receives malformed input.
     */
    private function sanitiseBlocks(array $blocks): array
    {
        return array_values(array_map(fn(array $block) => [
            'type' => $block['type'] ?? 'text',
            'data' => $block['data'] ?? [],
            'visible' => (bool)($block['visible'] ?? true),
        ], $blocks));
    }

    private function ensureUniqueSlug(string $slug, int $siteId, ?int $excludeId = null): string
    {
        $base = $slug;
        $counter = 1;

        while ($this->repository->slugExistsForSite($slug, $siteId, $excludeId)) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }

    public function update(int $id, array $data): NewsletterLayout
    {
        return $this->db->transaction(function () use ($id, $data) {
            return $this->apply($id, $data);
        });
    }

    // ── Preview ───────────────────────────────────────────────────────────────

    /**
     * Build a snapshot array and persist it as a new version.
     * Must be called from within a transaction.
     */
    private function recordVersion(NewsletterLayout $template): Model
    {
        $nextNumber = $this->versionRepository->maxVersionNumber($template->id) + 1;

        return $this->versionRepository->createVersion(
            templateId: $template->id,
            versionNumber: $nextNumber,
            snapshot: $this->buildSnapshot($template),
            createdBy: Auth::id(),
        );
    }

    /**
     * Snapshot shape — stored in snapshot_json and exposed to the frontend
     * diff viewer. Must include every field that appears in the edit form.
     */
    private function buildSnapshot(NewsletterLayout $template): array
    {
        $layoutDefinition = $template->layout_definition_json;

        return [
            'name' => $template->name,
            'slug' => $template->slug,
            'description' => $template->description ?? '',
            'category' => $template->category ?? '',
            'blocks' => $layoutDefinition['email_template']['blocks'] ?? [],
            'use_default_theme' => $template->use_default_theme ?? true,
            'theme_id' => $template->theme_id,
            'is_active' => $template->is_active ?? false,
        ];
    }

    public function delete(int $id): bool
    {
        $template = $this->repository->find($id);

        if (!$template) {
            throw new \RuntimeException("Email template {$id} not found.");
        }

        // Single write — no transaction required.
        return $template->delete();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    public function duplicate(int $id, string $newName): NewsletterLayout
    {
        return $this->db->transaction(function () use ($id, $newName) {
            $source = $this->repository->find($id);

            if (!$source) {
                throw new \RuntimeException("Email template {$id} not found.");
            }

            $slug = $this->ensureUniqueSlug(Str::slug($newName), $source->site_id);
            $template = $this->repository->create([
                'site_id' => $source->site_id,
                'theme_id' => $source->theme_id,
                'name' => $newName,
                'slug' => $slug,
                'description' => $source->description,
                'category' => $source->category,
                'layout_definition_json' => [
                    'email_template' => [
                        'blocks' => $source->blocks ?? []
                    ]
                ],
                'use_default_theme' => $source->use_default_theme ?? true,
                'is_active' => false, // duplicates start inactive
            ]);

            $this->recordVersion($template);

            return $template;
        });
    }

    public function create(array $data, int $siteId): NewsletterLayout
    {
        return $this->db->transaction(function () use ($data, $siteId) {
            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name'] ?? '');
            }

            $data['slug'] = $this->ensureUniqueSlug($data['slug'], $siteId);
            $data['site_id'] = $siteId;
            $data['layout_definition_json'] = [
                'email_template' => [
                    'blocks' => $this->sanitiseBlocks($data['blocks'] ?? []),
                    'description' => $data['description'],
                    'category' => $data['category']
                ]
            ];
            $data['type'] = 'email_template';

            $template = $this->repository->create($data);

            $this->recordVersion($template);

            return $template;
        });
    }

    /**
     * Render a preview of a saved template with a predefined dataset.
     *
     * @param string $dataset mock_user|mock_order|mock_seller
     */
    public function previewSaved(int $id, string $dataset): array
    {
        $template = $this->repository->find($id);

        if (!$template) {
            throw new \RuntimeException("Email template {$id} not found.");
        }

        $theme = $this->resolveTheme($template);
        $runtimeData = $this->previewDataFactory->build($dataset);
        $html = $this->renderer->render($template, $runtimeData, $theme);

        return $this->buildPreviewResult($html);
    }

    private function resolveTheme(NewsletterLayout $template): ?EmailTheme
    {
        if ($template->use_default_theme !== false) {
            return null; // EmailTemplateRenderer falls back to site default when null
        }

        return $template->theme_id ? $this->themeRepository->find($template->theme_id) : null;
    }

    /**
     * Render a template to HTML for dispatch (used by email send pipeline).
     */
    public function render(int $id, array $runtimeData = [], ?EmailTheme $theme = null): string
    {
        $template = $this->repository->find($id);

        if ($template === null) {
            throw new \RuntimeException("Email template {$id} not found.");
        }

        return $this->renderer->render($template, $runtimeData, $theme);
    }

    private function buildPreviewResult(string $html): array
    {
        return [
            'html' => $html,
            'plain_text' => $this->toPlainText($html),
            'unresolved_tokens' => $this->findUnresolvedTokens($html),
        ];
    }

    /**
     * Strip HTML tags to produce a plain-text fallback.
     */
    private function toPlainText(string $html): string
    {
        $text = preg_replace('/<(br|p|tr|li|div|h[1-6])[^>]*>/i', "\n", $html) ?? $html;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * After interpolation, any remaining {{ token }} patterns are unresolved.
     */
    private function findUnresolvedTokens(string $html): array
    {
        preg_match_all('/\{\{\s*([\w.]+)\s*\}\}/', $html, $matches);
        return array_unique($matches[1] ?? []);
    }

    /**
     * Render a live preview from unsaved editor block data.
     *
     * @param array $blocks Raw [{type, data, visible}] array
     * @param string $dataset mock_user|mock_order|mock_seller
     * @param int $siteId
     * @param int|null $themeId
     */
    public function previewLive(array $blocks, string $dataset, int $siteId, ?int $themeId = null): array
    {
        $theme = $themeId ? $this->themeRepository->find($themeId) : null;
        $runtimeData = $this->previewDataFactory->build($dataset);
        $html = $this->renderer->renderPreview($blocks, $runtimeData, $siteId, $theme);

        return $this->buildPreviewResult($html);
    }
}