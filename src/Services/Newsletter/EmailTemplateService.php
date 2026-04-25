<?php

namespace App\Services\Newsletter;

use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Framework\Support\Str;
use App\Models\EmailTemplate;
use App\Models\EmailTheme;
use App\Repositories\Newsletters\EmailTemplateRepository;
use App\Repositories\Newsletters\EmailThemeRepository;

/**
 * Orchestrates the EmailTemplate lifecycle.
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
        private readonly Database                $db,
        private readonly EmailTemplateRepository $repository,
        private readonly EmailThemeRepository    $themeRepository,
        private readonly EmailTemplateRenderer   $renderer,
        private readonly PreviewDataFactory      $previewDataFactory,
    )
    {
    }

    // ── Read ──────────────────────────────────────────────────

    public function getAllForSite(int $siteId, ?string $category = null): Collection
    {
        return $this->repository->getAllBySite($siteId, $category);
    }

    public function getById(int $id): ?EmailTemplate
    {
        return $this->repository->find($id);
    }

    public function getBySlug(string $slug, int $siteId): ?EmailTemplate
    {
        return $this->repository->findBySlug($slug, $siteId);
    }

    // ── Write ─────────────────────────────────────────────────

    public function update(int $id, array $data): EmailTemplate
    {
        return $this->db->transaction(function () use ($id, $data) {
            $template = $this->repository->find($id);

            if (!$template) {
                throw new \RuntimeException("Email template {$id} not found.");
            }

            if (isset($data['blocks'])) {
                $data['blocks'] = $this->sanitiseBlocks($data['blocks']);
            }

            // Regenerate slug if name changed and no explicit slug provided
            if (isset($data['name']) && $data['name'] !== $template->name && !isset($data['slug'])) {
                $data['slug'] = $this->ensureUniqueSlug(
                    Str::slug($data['name']),
                    $template->site_id,
                    $id
                );
            }

            return $this->repository->update($id, $data);
        });
    }

    /**
     * Strip unknown block types and ensure required keys are present.
     * The block registry validates types; we just ensure the array structure is sound.
     */
    private function sanitiseBlocks(array $blocks): array
    {
        return array_values(array_map(function (array $block) {
            return [
                'type' => $block['type'] ?? 'text',
                'data' => $block['data'] ?? [],
                'visible' => (bool)($block['visible'] ?? true),
            ];
        }, $blocks));
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

    public function delete(int $id): bool
    {
        $template = $this->repository->find($id);

        if (!$template) {
            throw new \RuntimeException("Email template {$id} not found.");
        }

        // No transaction needed — single write
        return $template->delete();
    }

    // ── Preview ───────────────────────────────────────────────

    public function duplicate(int $id, string $newName): EmailTemplate
    {
        return $this->db->transaction(function () use ($id, $newName) {
            $source = $this->repository->find($id);

            if (!$source) {
                throw new \RuntimeException("Email template {$id} not found.");
            }

            $slug = $this->ensureUniqueSlug(Str::slug($newName), $source->site_id);

            return $this->repository->create([
                'site_id' => $source->site_id,
                'theme_id' => $source->theme_id,
                'name' => $newName,
                'slug' => $slug,
                'description' => $source->description,
                'category' => $source->category,
                'blocks' => $source->blocks ?? [],
                'is_active' => false, // duplicates start inactive
            ]);
        });
    }

    public function create(array $data, int $siteId): EmailTemplate
    {
        return $this->db->transaction(function () use ($data, $siteId) {
            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name'] ?? '');
            }

            $data['slug'] = $this->ensureUniqueSlug($data['slug'], $siteId);
            $data['site_id'] = $siteId;
            $data['blocks'] = $this->sanitiseBlocks($data['blocks'] ?? []);

            return $this->repository->create($data);
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

        $theme = $template->theme_id ? $this->themeRepository->find($template->theme_id) : null;
        $runtimeData = $this->previewDataFactory->build($dataset);

        $html = $this->renderer->render($template, $runtimeData, $theme);

        return [
            'html' => $html,
            'plain_text' => $this->toPlainText($html),
            'unresolved_tokens' => $this->findUnresolvedTokens($html),
        ];
    }

    // ── Private helpers ───────────────────────────────────────

    public function render(int $id, array $runtimeData = [], ?EmailTheme $theme = null): string
    {
        $template = $this->repository->find($id);

        if ($template === null) {
            throw new \RuntimeException("Email template {$id} not found.");
        }

        return $this->renderer->render($template, $runtimeData, $theme);
    }

    /**
     * Strip HTML tags to produce a plain-text fallback.
     * Not a full Markdown conversion — good enough for email clients that strip HTML.
     */
    private function toPlainText(string $html): string
    {
        // Preserve line breaks from block-level elements
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

        return [
            'html' => $html,
            'plain_text' => $this->toPlainText($html),
            'unresolved_tokens' => $this->findUnresolvedTokens($html),
        ];
    }
}
