<?php

namespace App\Services;

use App\Framework\Support\SiteContext;

class ThemeService
{
    private array $themeConfig = [];
    private string $currentTheme;

    public function __construct()
    {
        $this->currentTheme = SiteContext::getTheme();
        $this->loadThemeConfig();
    }

    /**
     * Load theme configuration
     */
    private function loadThemeConfig(): void
    {
        $configPath = __DIR__ . "/../../config/themes/{$this->currentTheme}.php";

        if (file_exists($configPath)) {
            $this->themeConfig = require $configPath;
        } else {
            // Load default theme config
            $defaultConfigPath = __DIR__ . "/../../config/themes/default.php";
            if (file_exists($defaultConfigPath)) {
                $this->themeConfig = require $defaultConfigPath;
            }
        }
    }

    /**
     * Get theme CSS URL
     */
    public function getCssUrl(): string
    {
        return "/assets/css/themes/{$this->currentTheme}.css";
    }

    /**
     * Get additional theme CSS files
     */
    public function getAdditionalCss(): array
    {
        return $this->themeConfig['additional_css'] ?? [];
    }

    /**
     * Get theme JavaScript files
     */
    public function getJsFiles(): array
    {
        return $this->themeConfig['js_files'] ?? [];
    }

    /**
     * Get theme config value
     */
    public function getConfig(string $key, $default = null)
    {
        return $this->themeConfig[$key] ?? $default;
    }

    /**
     * Get theme colors
     */
    public function getColors(): array
    {
        return $this->themeConfig['colors'] ?? [
            'primary' => '#007bff',
            'secondary' => '#6c757d',
            'success' => '#28a745',
            'danger' => '#dc3545',
            'warning' => '#ffc107',
            'info' => '#17a2b8'
        ];
    }

    /**
     * Get specific color
     */
    public function getColor(string $name): ?string
    {
        $colors = $this->getColors();
        return $colors[$name] ?? null;
    }

    /**
     * Get theme fonts
     */
    public function getFonts(): array
    {
        return $this->themeConfig['fonts'] ?? [
            'primary' => "'Roboto', sans-serif",
            'heading' => "'Montserrat', sans-serif"
        ];
    }

    /**
     * Get theme layout
     */
    public function getLayout(): string
    {
        return $this->themeConfig['layout'] ?? 'default';
    }

    /**
     * Get theme features
     */
    public function getFeatures(): array
    {
        return $this->themeConfig['features'] ?? [];
    }

    /**
     * Check if theme has specific feature
     */
    public function hasFeature(string $feature): bool
    {
        $features = $this->getFeatures();
        return in_array($feature, $features);
    }

    /**
     * Get theme view path
     */
    public function getViewPath(string $view): string
    {
        return "{$this->currentTheme}/{$view}";
    }

    /**
     * Check if theme exists
     */
    public function exists(): bool
    {
        $cssPath = __DIR__ . "/../../public/assets/css/themes/{$this->currentTheme}.css";
        return file_exists($cssPath);
    }

    /**
     * Get available themes
     */
    public static function getAvailableThemes(): array
    {
        $themesDir = __DIR__ . '/../../public/assets/css/themes/';
        $themes = [];

        if (is_dir($themesDir)) {
            $files = scandir($themesDir);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'css') {
                    $themeName = pathinfo($file, PATHINFO_FILENAME);
                    $themes[$themeName] = self::getThemeInfo($themeName);
                }
            }
        }

        return $themes;
    }

    /**
     * Get theme information
     */
    private static function getThemeInfo(string $themeName): array
    {
        $configPath = __DIR__ . "/../../config/themes/{$themeName}.php";

        if (file_exists($configPath)) {
            $config = require $configPath;
            return [
                'name' => $config['name'] ?? ucfirst($themeName),
                'description' => $config['description'] ?? '',
                'version' => $config['version'] ?? '1.0.0',
                'author' => $config['author'] ?? '',
                'preview' => $config['preview'] ?? null
            ];
        }

        return [
            'name' => ucfirst($themeName),
            'description' => '',
            'version' => '1.0.0'
        ];
    }
}