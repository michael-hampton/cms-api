<?php

namespace App\Framework\Support;

use App\Models\Site;

class SiteContext
{
    private static ?Site $currentSite = null;
    private static bool $resolved = false;
    private static array $config = [];


    /**
     * Resolve and set the current site based on domain or path
     */
    public static function resolve(string $host, string $path): Site
    {
        if (self::$resolved && self::$currentSite) {
            return self::$currentSite;
        }

        // Remove port from host
        $host = strtok($host, ':');

        // Try to resolve by domain first (for production)
        $site = self::resolveByDomain($host);

        // Try subdomain matching
        if (!$site) {
            $site = self::resolveBySubdomain($host);
        }

        // If localhost, try path-based resolution
        if (!$site && self::isLocalhost($host)) {
            $site = self::resolveByPath($path);
        }

        // Fallback to default site
        if (!$site) {
            $site = self::getDefaultSite();
        }

        if (!$site) {
            throw new \RuntimeException('No active site found in database');
        }

        self::set($site);
        return $site;
    }

    /**
     * Resolve site by domain
     */
    private static function resolveByDomain(string $host): ?Site
    {
        // Remove port if present
        $host = strtok($host, ':');

        return Site::where('domain', $host)
            ->where('is_active', 1)
            ->first();
    }

    /**
     * Resolve site by subdomain
     */
    private static function resolveBySubdomain(string $host): ?Site
    {
        $subdomain = self::extractSubdomain($host);

        if (!$subdomain) {
            return null;
        }

        return Site::where('subdomain', $subdomain)
            ->where('is_active', 1)
            ->first();
    }

    /**
     * Resolve site by path (for localhost development)
     */
    private static function resolveByPath(string $path): ?Site
    {
        // Extract first path segment: /site-slug/page -> site-slug
        $segments = array_filter(explode('/', $path));

        if (empty($segments)) {
            return null;
        }

        $siteSlug = reset($segments);

        return Site::where('slug', $siteSlug)
            ->where('is_active', 1)
            ->first();
    }

    /**
     * Extract subdomain from host
     */
    private static function extractSubdomain(string $host): ?string
    {
        // Split by dots
        $parts = explode('.', $host);

        // If localhost or single domain, no subdomain
        if (count($parts) < 3 || $host === 'localhost') {
            return null;
        }

        // Return first part as subdomain (e.g., 'music' from 'music.example.com')
        return $parts[0];
    }

    /**
     * Get default site
     */
    private static function getDefaultSite(): ?Site
    {
        // Try to get site marked as default
        $site = Site::where('is_default', 1)
            ->where('is_active', 1)
            ->first();

        // Fallback to config default
        if (!$site) {
            $defaultId = config('app.default_site_id', 1);
            $site = Site::find($defaultId);
        }

        // Last resort: get first active site
        if (!$site) {
            $site = Site::where('is_active', 1)->first();
        }

        return $site;
    }

    /**
     * Check if host is localhost
     */
    public static function isLocalhost(string $host): bool
    {
        $host = strtok($host, ':');
        return in_array($host, ['localhost', '127.0.0.1', '::1']);
    }

    /**
     * Set the current site
     */
    public static function set(?Site $site): void
    {
        self::$currentSite = $site;

        if(empty($site)) {
            return;
        }

        self::$resolved = true;
        self::loadSiteConfig($site);
    }

    /**
     * Load site-specific configuration
     */
    private static function loadSiteConfig(Site $site): void
    {
        self::$config = [
            'site_id' => $site->id,
            'site_name' => $site->name,
            'domain' => $site->domain,
            'subdomain' => $site->subdomain,
            'slug' => $site->slug,
            'theme' => $site->theme ?? 'default',
            'logo' => $site->logo,
            'favicon' => $site->favicon,
            'settings' => $site->settings ? (is_string($site->settings) ? json_decode($site->settings, true) : $site->settings) : []
        ];
    }

    /**
     * Get the current site
     */
    public static function get(): ?Site
    {
        return self::$currentSite;
    }

    /**
     * Get current site ID
     */
    public static function getId(): ?int
    {
        return self::$currentSite?->id;
    }

    /**
     * Get site configuration value
     */
    public static function getConfig(?string $key = null)
    {
        if ($key === null) {
            return self::$config;
        }

        return self::$config[$key] ?? null;
    }

    /**
     * Get site setting
     */
    public static function getSetting(string $key, $default = null)
    {
        return self::$config['settings'][$key] ?? $default;
    }

    /**
     * Get site theme
     */
    public static function getTheme(): string
    {
        return self::$config['theme'] ?? 'default';
    }

    /**
     * Check if site context is resolved
     */
    public static function isResolved(): bool
    {
        return self::$resolved;
    }

    /**
     * Clear the current site (useful for testing)
     */
    public static function clear(): void
    {
        self::$currentSite = null;
        self::$resolved = false;
        self::$config = [];
    }

    /**
     * Get site URL (without path)
     */
    public static function getUrl(): string
    {
        if (!self::$currentSite) {
            return config('app.url', '');
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $host = strtok($host, ':'); // Remove port

        // If has custom domain and not localhost, use it
        if (self::$currentSite->domain && !self::isLocalhost($host)) {
            return "{$protocol}://" . self::$currentSite->domain;
        }

        // If has subdomain
        if (self::$currentSite->subdomain) {
            $baseDomain = config('app.base_domain', 'example.com');
            return "{$protocol}://" . self::$currentSite->subdomain . '.' . $baseDomain;
        }

        // For localhost, include site slug in path
        if (self::isLocalhost($host)) {
            $port = $_SERVER['SERVER_PORT'] ?? '80';
            $portString = ($port != '80' && $port != '443') ? ":{$port}" : '';
            return "{$protocol}://{$host}{$portString}/" . self::$currentSite->slug;
        }

        return "{$protocol}://{$host}";
    }

    /**
     * Generate site-specific URL
     */
    public static function url(string $path = ''): string
    {
        $baseUrl = self::getUrl();
        $path = ltrim($path, '/');
        return $baseUrl . ($path ? '/' . $path : '');
    }

    /**
     * Get site-specific asset path
     */
    public static function asset(string $path): string
    {
        $siteSlug = self::$currentSite?->slug ?? 'default';
        $path = ltrim($path, '/');
        return "/assets/sites/{$siteSlug}/{$path}";
    }

    /**
     * Get site-specific CSS file
     */
    public static function css(): string
    {
        $theme = self::getTheme();
        return "/themes/{$theme}.css";
    }

    /**
     * Get site logo URL
     */
    public static function logo(): ?string
    {
        return self::$config['logo'] ?? null;
    }

    /**
     * Get site favicon URL
     */
    public static function favicon(): ?string
    {
        return self::$config['favicon'] ?? null;
    }

    /**
     * Get site name
     */
    public static function name(): string
    {
        return self::$config['site_name'] ?? 'My Site';
    }

    /**
     * Check if current site matches given site ID
     */
    public static function isSite(int $siteId): bool
    {
        return self::getId() === $siteId;
    }

    /**
     * Switch site context (for admin/testing purposes)
     */
    public static function switchSite(int $siteId): bool
    {
        $site = Site::where('id', $siteId)
            ->where('is_active', 1)
            ->first();

        if ($site) {
            self::set($site);
            return true;
        }

        return false;
    }

    /**
     * Get all active sites
     */
    public static function getAllSites(): array
    {
        return Site::where('is_active', 1)->get()->toArray();
    }
}