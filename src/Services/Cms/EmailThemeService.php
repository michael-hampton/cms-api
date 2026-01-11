<?php

namespace App\Services\Cms;

use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Framework\Support\Str;
use App\Models\EmailTheme;
use App\Models\EmailThemeAsset;
use App\Models\EmailThemeColor;
use App\Models\EmailThemeFont;
use App\Models\EmailThemeSetting;
use App\Repositories\Cms\EmailThemeRepository;
use App\Search\PaginatedResult;
use App\Search\SearchCriteria;

class EmailThemeService
{
    public function __construct(
        private Database             $db,
        private EmailThemeRepository $repository,
        private ImageUploadService   $imageUploadService
    )
    {
    }

    public function getAllThemes(int $siteId): Collection
    {
        return $this->repository->getAllBySite($siteId);
    }

    public function getActiveThemes(int $siteId): Collection
    {
        return $this->repository->getActiveBySite($siteId);
    }

    public function getThemeById(int $id): ?EmailTheme
    {
        return $this->repository->find($id);
    }

    public function getThemeBySlug(string $slug, int $siteId): ?EmailTheme
    {
        return $this->repository->findBySlug($slug, $siteId);
    }

    public function getDefaultTheme(int $siteId): ?EmailTheme
    {
        return $this->repository->getDefaultForSite($siteId);
    }

    public function createTheme(array $data, int $siteId, $logoFile = null): EmailTheme
    {
        return $this->db->transaction(function () use ($data, $siteId, $logoFile) {
            // Generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }

            // Ensure unique slug
            $data['slug'] = $this->ensureUniqueSlug($data['slug'], $siteId);

            $data['site_id'] = $siteId;

            // Handle logo upload
            if ($logoFile && $logoFile->isValid()) {
                $logoPath = $this->imageUploadService->upload($logoFile);
                if (!isset($data['assets'])) {
                    $data['assets'] = [];
                }
                $data['assets']['logo'] = array_merge(
                    $data['assets']['logo'] ?? [],
                    ['url' => $logoPath]
                );
            }

            // If this is set as default, unset others
            if ($data['is_default'] ?? false) {
                EmailTheme::bySite($siteId)->update(['is_default' => false]);
            }

            // Extract related data
            $colors = $data['colors'] ?? [];
            $fonts = $data['fonts'] ?? [];
            $assets = $data['assets'] ?? [];
            $settings = $data['settings'] ?? [];

            unset($data['colors'], $data['fonts'], $data['assets'], $data['settings']);

            // Create theme
            $theme = $this->repository->create($data);

            // Create related records
            $this->syncColors($theme->id, $colors);
            $this->syncFonts($theme->id, $fonts);
            $this->syncAssets($theme->id, $assets);
            $this->syncSettings($theme->id, $settings);

            return $this->repository->find($theme->id, ['assets', 'colors', 'fonts', 'settings']);
        });
    }

    private function ensureUniqueSlug(string $slug, int $siteId, ?int $excludeId = null): string
    {
        $originalSlug = $slug;
        $counter = 1;

        while (true) {
            $query = EmailTheme::bySlug($slug)->where('site_id', $siteId);

            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            if (!$query->exists()) {
                break;
            }

            $slug = $originalSlug . '-' . $counter++;
        }

        return $slug;
    }

    private function syncColors(int $themeId, array $colors): void
    {
        // Delete existing
        EmailThemeColor::where('theme_id', $themeId)->delete();

        // Insert new
        foreach ($colors as $key => $value) {
            EmailThemeColor::create([
                'theme_id' => $themeId,
                'color_key' => $key,
                'color_value' => $value
            ]);
        }
    }

    private function syncFonts(int $themeId, array $fonts): void
    {
        EmailThemeFont::where('theme_id', $themeId)->delete();

        foreach ($fonts as $key => $font) {
            EmailThemeFont::create([
                'theme_id' => $themeId,
                'font_key' => $key,
                'font_family' => $font['family'] ?? '',
                'font_size' => $font['size'] ?? null,
                'font_weight' => $font['weight'] ?? null
            ]);
        }
    }

    private function syncAssets(int $themeId, array $assets): void
    {
        EmailThemeAsset::where('theme_id', $themeId)->delete();

        foreach ($assets as $key => $asset) {
            EmailThemeAsset::create([
                'theme_id' => $themeId,
                'asset_key' => $key,
                'asset_type' => $asset['type'] ?? 'image',
                'asset_url' => $asset['url'],
                'alt_text' => $asset['alt'] ?? null,
                'width' => $asset['width'] ?? null,
                'height' => $asset['height'] ?? null
            ]);
        }
    }

    private function syncSettings(int $themeId, array $settings): void
    {
        EmailThemeSetting::where('theme_id', $themeId)->delete();

        foreach ($settings as $key => $value) {
            $type = is_bool($value) ? 'boolean' : (is_numeric($value) ? 'number' : 'string');
            EmailThemeSetting::create([
                'theme_id' => $themeId,
                'setting_key' => $key,
                'setting_value' => (string)$value,
                'setting_type' => $type
            ]);
        }
    }

    public function updateTheme(int $id, array $data, $logoFile = null): EmailTheme
    {
        return $this->db->transaction(function () use ($id, $data, $logoFile) {
            $theme = $this->repository->find($id);

            if (!$theme) {
                throw new \Exception('Theme not found');
            }

            // Regenerate slug if name changed
            if (isset($data['name']) && $data['name'] !== $theme->name) {
                $data['slug'] = $this->ensureUniqueSlug(
                    Str::slug($data['name']),
                    $theme->site_id,
                    $id
                );
            }

            // Handle logo upload
            if ($logoFile && $logoFile->isValid()) {
                // Delete old logo if exists
                $oldAssets = $theme->getAssets();
                if (isset($oldAssets['logo']['url'])) {
                    $this->imageUploadService->delete($oldAssets['logo']['url']);
                }

                $logoPath = $this->imageUploadService->upload($logoFile);
                if (!isset($data['assets'])) {
                    $data['assets'] = [];
                }
                $data['assets']['logo'] = array_merge(
                    $data['assets']['logo'] ?? [],
                    ['url' => $logoPath]
                );
            }

            // If this is set as default, unset others
            if (($data['is_default'] ?? false) && !$theme->is_default) {
                EmailTheme::bySite($theme->site_id)
                    ->where('id', '!=', $id)
                    ->update(['is_default' => false]);
            }

            // Extract related data
            $colors = $data['colors'] ?? null;
            $fonts = $data['fonts'] ?? null;
            $assets = $data['assets'] ?? null;
            $settings = $data['settings'] ?? null;

            unset($data['colors'], $data['fonts'], $data['assets'], $data['settings']);

            // Update theme
            $theme = $this->repository->update($id, $data);

            // Update related records if provided
            if ($colors !== null) {
                $this->syncColors($id, $colors);
            }
            if ($fonts !== null) {
                $this->syncFonts($id, $fonts);
            }
            if ($assets !== null) {
                $this->syncAssets($id, $assets);
            }
            if ($settings !== null) {
                $this->syncSettings($id, $settings);
            }

            return $theme->fresh();
        });
    }

    public function deleteTheme(int $id): bool
    {
        $theme = $this->repository->find($id);

        if (!$theme) {
            throw new \Exception('Theme not found');
        }

        if ($theme->is_default) {
            throw new \Exception('Cannot delete default theme. Please set another theme as default first.');
        }

        // Delete logo if exists
        $assets = $theme->getAssets();
        if (isset($assets['logo']['url'])) {
            $this->imageUploadService->delete($assets['logo']['url']);
        }

        return $theme->delete();
    }

    public function setDefaultTheme(int $themeId, int $siteId): bool
    {
        return $this->repository->setDefaultTheme($themeId, $siteId);
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        return $this->repository->search($criteria);
    }

    public function getAlternativeThemes(int $excludeId, int $siteId): Collection
    {
        return $this->repository->getAlternatives($excludeId, $siteId);
    }
}