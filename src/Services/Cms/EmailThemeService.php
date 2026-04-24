<?php

namespace App\Services\Cms;

use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Framework\Support\Str;
use App\Models\EmailTheme;
use App\Models\EmailThemeColor;
use App\Models\EmailThemeFont;
use App\Repositories\Cms\EmailThemeAssetRepository;
use App\Repositories\Cms\EmailThemeRepository;
use App\Repositories\Cms\EmailThemeSettingRepository;
use App\Search\PaginatedResult;
use App\Search\SearchCriteria;

class EmailThemeService
{
    public function __construct(
        private readonly Database                    $db,
        private readonly EmailThemeRepository        $repository,
        private readonly ImageUploadService          $imageUploadService,
        private readonly EmailThemeAssetRepository   $emailThemeAssetRepository,
        private readonly EmailThemeSettingRepository $emailThemeSettingRepository
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

    /**
     * Return the resolved variable map for a theme.
     *
     * Used by the frontend editor to populate its initial state from a
     * saved theme without re-deriving the structure itself.
     */
    public function getThemeVariables(int $id): array
    {
        $theme = $this->repository->find($id, ['assets', 'colors', 'fonts', 'settings']);

        if (!$theme) {
            throw new \Exception('Theme not found');
        }

        $colors = [];
        foreach ($theme->colors as $item) {
            $colors[$item->color_key] = $item->color_value;
        }

        $fonts = [];
        foreach ($theme->fonts as $item) {
            $fonts[$item->font_key] = [
                'family' => $item->font_family,
                'size' => $item->font_size,
                'weight' => $item->font_weight,
            ];
        }

        $settings = [];
        foreach ($theme->settings as $item) {
            $raw = $item->setting_value;
            $settings[$item->setting_key] = match ($item->setting_type) {
                'boolean' => in_array(strtolower((string)$raw), ['1', 'true', 'yes'], true),
                'number' => (int)$raw,
                default => $raw,
            };
        }

        $assets = [];
        foreach ($theme->assets as $item) {
            $assets[$item->asset_key] = [
                'type' => $item->asset_type,
                'url' => $item->asset_url,
                'alt' => $item->alt_text,
                'width' => $item->width,
                'height' => $item->height,
            ];
        }

        return [
            'id' => $theme->id,
            'name' => $theme->name,
            'slug' => $theme->slug,
            'description' => $theme->description,
            'is_active' => $theme->is_active,
            'is_default' => $theme->is_default,
            'colors' => $colors,
            'fonts' => $fonts,
            'settings' => $settings,
            'assets' => $assets,
        ];
    }

    public function createTheme(array $data, int $siteId, $logoFile = null): EmailTheme
    {
        return $this->db->transaction(function () use ($data, $siteId, $logoFile) {
            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }

            $data['slug'] = $this->ensureUniqueSlug($data['slug'], $siteId);
            $data['site_id'] = $siteId;

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

            if ($data['is_default'] ?? false) {
                EmailTheme::bySite($siteId)->update(['is_default' => false]);
            }

            $colors = $data['colors'] ?? [];
            $fonts = $data['fonts'] ?? [];
            $assets = $data['assets'] ?? [];
            $settings = $data['settings'] ?? [];

            unset($data['colors'], $data['fonts'], $data['assets'], $data['settings']);

            $theme = $this->repository->create($data);

            $this->syncColors($theme->id, $colors);
            $this->syncFonts($theme->id, $fonts);
            $this->syncAssets($theme->id, $assets);
            $this->syncSettings($theme->id, $settings);

            return $this->repository->find($theme->id, ['assets', 'colors', 'fonts', 'settings']);
        });
    }

    public function updateTheme(int $id, array $data, $logoFile = null): EmailTheme
    {
        return $this->db->transaction(function () use ($id, $data, $logoFile) {
            $theme = $this->repository->find($id);

            if (!$theme) {
                throw new \Exception('Theme not found');
            }

            if (isset($data['name']) && $data['name'] !== $theme->name) {
                $data['slug'] = $this->ensureUniqueSlug(
                    Str::slug($data['name']),
                    $theme->site_id,
                    $id
                );
            }

            if ($logoFile && $logoFile->isValid()) {
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

            if (($data['is_default'] ?? false) && !$theme->is_default) {
                EmailTheme::bySite($theme->site_id)
                    ->where('id', '!=', $id)
                    ->update(['is_default' => false]);
            }

            $colors = $data['colors'] ?? null;
            $fonts = $data['fonts'] ?? null;
            $assets = $data['assets'] ?? null;
            $settings = $data['settings'] ?? null;

            unset($data['colors'], $data['fonts'], $data['assets'], $data['settings']);

            $theme = $this->repository->update($id, $data);

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

    // -------------------------------------------------------------------------
    // Private sync helpers
    // -------------------------------------------------------------------------

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
        EmailThemeColor::where('theme_id', $themeId)->delete();

        foreach ($colors as $key => $value) {
            EmailThemeColor::create([
                'theme_id' => $themeId,
                'color_key' => $key,
                'color_value' => $value,
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
                'font_weight' => $font['weight'] ?? null,
            ]);
        }
    }

    private function syncAssets(int $themeId, array $assets): void
    {
        $this->emailThemeAssetRepository->deleteAssetsForTheme($themeId);

        foreach ($assets as $key => $asset) {
            $this->emailThemeAssetRepository->create([
                'theme_id' => $themeId,
                'asset_key' => $key,
                'asset_type' => $asset['type'] ?? 'image',
                'asset_url' => $asset['url'],
                'alt_text' => $asset['alt'] ?? null,
                'width' => $asset['width'] ?? null,
                'height' => $asset['height'] ?? null,
            ]);
        }
    }

    private function syncSettings(int $themeId, array $settings): void
    {
        $this->emailThemeSettingRepository->deleteSettingsForTheme($themeId);

        foreach ($settings as $key => $value) {
            $type = is_bool($value) ? 'boolean' : (is_numeric($value) ? 'number' : 'string');
            $this->emailThemeSettingRepository->create([
                'theme_id' => $themeId,
                'setting_key' => $key,
                'setting_value' => (string)$value,
                'setting_type' => $type,
            ]);
        }
    }
}