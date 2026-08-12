<?php

namespace App\Services\Newsletter;

use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Framework\Support\Str;
use App\Models\NewsletterBrandingConfiguration;
use App\Repositories\Newsletters\EmailThemeRepository;
use App\Repositories\Newsletters\NewsletterBrandingRepository;
use App\Search\PaginatedResult;
use App\Search\SearchCriteria;
use App\Services\Cms\ImageUploadService;

/**
 * Thin service adapter.
 *
 * All EmailTheme controller endpoints continue to call this class unchanged.
 * Internally every operation delegates to NewsletterBrandingRepository so
 * there is no duplicated persistence logic.
 *
 * Theme data (colors / fonts / assets / settings) is stored in and read from
 * theme_json on NewsletterBrandingConfiguration.
 */
class EmailThemeService
{
    public function __construct(
        private readonly Database                     $db,
        private readonly EmailThemeRepository        $repository,
        private readonly NewsletterBrandingRepository $brandingRepository,
        private readonly ImageUploadService          $imageUploadService,
    )
    {
    }

    // =========================================================================
    // Read
    // =========================================================================

    public function getAllThemes(int $siteId): Collection
    {
        return $this->repository->getAllBySite($siteId);
    }

    public function getActiveThemes(int $siteId): Collection
    {
        return $this->repository->getActiveBySite($siteId);
    }

    public function getThemeById(int $id): ?NewsletterBrandingConfiguration
    {
        return $this->repository->find($id);
    }

    public function getThemeBySlug(string $slug, int $siteId): ?NewsletterBrandingConfiguration
    {
        return $this->repository->findBySlug($slug, $siteId);
    }

    public function getDefaultTheme(int $siteId): ?NewsletterBrandingConfiguration
    {
        return $this->repository->getDefaultForSite($siteId);
    }

    public function getAlternativeThemes(int $excludeId, int $siteId): Collection
    {
        return $this->repository->getAlternatives($excludeId, $siteId);
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        return $this->repository->search($criteria);
    }

    /**
     * Return the resolved variable map for a theme — used by the frontend
     * editor to populate its initial state without re-deriving the structure.
     */
    public function getThemeVariables(int $id): array
    {
        $theme = $this->repository->find($id);

        if (!$theme) {
            throw new \RuntimeException('Theme not found');
        }

        return [
            'id' => $theme->id,
            'name' => $theme->name,
            'slug' => $theme->slug,
            'description' => $theme->description,
            'is_active' => $theme->is_active,
            'is_default' => $theme->is_default,
            'colors' => $theme->getColors(),
            'fonts' => $theme->getFonts(),
            'settings' => $theme->getSettings(),
            'assets' => $theme->getAssets(),
        ];
    }

    // =========================================================================
    // Write
    // =========================================================================

    public function createTheme(array $data, int $siteId, mixed $logoFile = null): NewsletterBrandingConfiguration
    {
        return $this->db->transaction(function () use ($data, $siteId, $logoFile) {
            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name'] ?? '');
            }

            $data['slug'] = $this->ensureUniqueSlug($data['slug'], $siteId);
            $data['site_id'] = $siteId;
            $data['type'] = NewsletterBrandingConfiguration::TYPE_EMAIL_TEMPLATE;

            $data = $this->uploadLogoIfPresent($data, $logoFile);

            if ($data['is_default'] ?? false) {
                $this->clearSiteDefaults($siteId);
            }

            $themeJson = $this->extractThemeJson($data);
            $record = $this->buildRecord($data, $themeJson);

            return $this->repository->create($record);
        });
    }

    public function updateTheme(int $id, array $data, mixed $logoFile = null): NewsletterBrandingConfiguration
    {
        return $this->db->transaction(function () use ($id, $data, $logoFile) {
            $theme = $this->repository->find($id);

            if (!$theme) {
                throw new \RuntimeException('Theme not found');
            }

            if (isset($data['name']) && $data['name'] !== $theme->name && !isset($data['slug'])) {
                $data['slug'] = $this->ensureUniqueSlug(
                    Str::slug($data['name']),
                    $theme->site_id,
                    $id
                );
            }

            $data = $this->uploadLogoIfPresent($data, $logoFile, $theme);

            if (($data['is_default'] ?? false) && !$theme->is_default) {
                $this->clearSiteDefaults($theme->site_id, $id);
            }

            // Merge incoming theme_json sub-keys with what is already stored
            $existingJson = $theme->theme_json ?? [];
            $incomingJson = $this->extractThemeJson($data);
            $mergedJson = $this->mergeThemeJson($existingJson, $incomingJson, $data);

            $updates = $this->buildScalarUpdates($data);

            if (!empty($mergedJson)) {
                $updates['theme_json'] = $mergedJson;
            }

            // Propagate logo_url from assets into the top-level convenience column
            if (isset($mergedJson['assets']['logo']['url'])) {
                $updates['logo_url'] = $mergedJson['assets']['logo']['url'];
            }

            $this->repository->update($id, $updates);

            return $this->repository->find($id);
        });
    }

    public function deleteTheme(int $id): bool
    {
        $theme = $this->repository->find($id);

        if (!$theme) {
            throw new \RuntimeException('Theme not found');
        }

        if ($theme->is_default) {
            throw new \RuntimeException(
                'Cannot delete the default theme. Please set another theme as default first.'
            );
        }

        $assets = $theme->getAssets();
        if (isset($assets['logo']['url'])) {
            $this->imageUploadService->delete($assets['logo']['url']);
        }

        return (bool)$theme->delete();
    }

    public function setDefaultTheme(int $themeId, int $siteId): bool
    {
        return $this->brandingRepository->setDefaultTheme($themeId, $siteId);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Upload a logo file and inject the resulting URL into $data['assets']['logo'].
     * When a previous logo URL exists on $existingTheme, it is deleted first.
     */
    private function uploadLogoIfPresent(
        array                            $data,
        mixed                            $logoFile,
        ?NewsletterBrandingConfiguration $existingTheme = null
    ): array
    {
        if (!$logoFile || !$logoFile->isValid()) {
            return $data;
        }

        if ($existingTheme) {
            $oldAssets = $existingTheme->getAssets();
            if (isset($oldAssets['logo']['url'])) {
                $this->imageUploadService->delete($oldAssets['logo']['url']);
            }
        }

        $logoPath = $this->imageUploadService->upload($logoFile);

        $data['assets'] = $data['assets'] ?? [];
        $data['assets']['logo'] = array_merge(
            $data['assets']['logo'] ?? [],
            ['url' => $logoPath]
        );

        return $data;
    }

    /**
     * Extract colors / fonts / assets / settings from a flat data array and
     * return them as a theme_json array.  Any sub-key present is included;
     * absent sub-keys are omitted so callers can do partial updates.
     */
    private function extractThemeJson(array $data): array
    {
        $json = [];

        foreach (['colors', 'fonts', 'assets', 'settings'] as $key) {
            if (isset($data[$key])) {
                $json[$key] = $data[$key];
            }
        }

        return $json;
    }

    /**
     * Merge incoming theme_json partial update over the existing stored value.
     * Sub-keys absent from the incoming payload are preserved from existing.
     * If incoming payload contains a sub-key it fully replaces that sub-key.
     */
    private function mergeThemeJson(array $existing, array $incoming, array $data): array
    {
        if (empty($incoming)) {
            return $existing;
        }

        $merged = $existing;

        foreach (['colors', 'fonts', 'assets', 'settings'] as $key) {
            if (array_key_exists($key, $data)) {
                // Explicit null means "clear this sub-key"
                if ($data[$key] === null) {
                    unset($merged[$key]);
                } else {
                    $merged[$key] = $incoming[$key];
                }
            }
        }

        return $merged;
    }

    /**
     * Build the scalar columns to persist.  Strips theme_json sub-key fields
     * from $data so they are not written as top-level columns.
     */
    private function buildScalarUpdates(array $data): array
    {
        $scalar = array_diff_key($data, array_flip(['colors', 'fonts', 'assets', 'settings']));

        // logo_url is derived from assets — never set it directly from input
        unset($scalar['logo_url']);

        // NOT NULL booleans: null means "leave unchanged", not SQL NULL.
        foreach (['is_active', 'is_default'] as $booleanField) {
            if (array_key_exists($booleanField, $scalar) && $scalar[$booleanField] === null) {
                unset($scalar[$booleanField]);
            }
        }

        return $scalar;
    }

    /**
     * Assemble the full record array for a new theme.
     */
    private function buildRecord(array $data, array $themeJson): array
    {
        $scalar = $this->buildScalarUpdates($data);

        $record = array_merge($scalar, [
            'type' => NewsletterBrandingConfiguration::TYPE_EMAIL_TEMPLATE,
            'theme_json' => $themeJson,
        ]);

        // Propagate logo_url into the convenience column
        if (isset($themeJson['assets']['logo']['url'])) {
            $record['logo_url'] = $themeJson['assets']['logo']['url'];
        }

        return $record;
    }

    private function clearSiteDefaults(int $siteId, ?int $exceptId = null): void
    {
        $query = NewsletterBrandingConfiguration::emailTemplates()
            ->bySite($siteId);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update(['is_default' => 0]);
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
}