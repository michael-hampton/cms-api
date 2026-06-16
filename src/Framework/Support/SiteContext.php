<?php

namespace App\Framework\Support;

use App\Models\Site;

class SiteContext
{
    private static ?Site $currentSite = null;
    private static bool $resolved = false;
    private static array $config = [];

    public static function resolve(string $host, string $path): Site
    {
        if (self::$resolved && self::$currentSite) {
            return self::$currentSite;
        }

        $host = strtok($host, ':');
        $site = self::resolveByDomain($host);

        if (!$site) {
            $site = self::resolveBySubdomain($host);
        }

        if (!$site && self::isLocalhost($host)) {
            $site = self::resolveByPath($path);
        }

        if (!$site) {
            $site = self::getDefaultSite();
        }

        if (!$site) {
            throw new \RuntimeException('No active site found in database');
        }

        self::set($site);
        return $site;
    }

    private static function resolveByDomain(string $host): ?Site
    {
        $host = strtok($host, ':');

        return Site::where('domain', $host)
            ->where('is_active', 1)
            ->first();
    }

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

    private static function resolveByPath(string $path): ?Site
    {
        $segments = array_values(array_filter(explode('/', $path)));

        if ($segments === []) {
            return null;
        }

        if (($segments[0] ?? null) === 'api') {
            array_shift($segments);

            if (isset($segments[0]) && preg_match('/^v\d+$/i', $segments[0]) === 1) {
                array_shift($segments);
            }
        }

        $siteSlug = $segments[0] ?? null;

        if (!$siteSlug) {
            return null;
        }

        return Site::where('slug', $siteSlug)
            ->where('is_active', 1)
            ->first();
    }

    private static function extractSubdomain(string $host): ?string
    {
        $parts = explode('.', $host);

        if (count($parts) < 3 || $host === 'localhost') {
            return null;
        }

        return $parts[0];
    }

    private static function getDefaultSite(): ?Site
    {
        $site = Site::where('is_default', 1)
            ->where('is_active', 1)
            ->first();

        if (!$site) {
            $defaultId = config('app.default_site_id', 1);
            $site = Site::find($defaultId);
        }

        if (!$site) {
            $site = Site::where('is_active', 1)->first();
        }

        return $site;
    }

    public static function isLocalhost(string $host): bool
    {
        $host = strtok($host, ':');
        return in_array($host, ['localhost', '127.0.0.1', '::1']);
    }

    public static function set(?Site $site): void
    {
        self::$currentSite = $site;

        if (empty($site)) {
            return;
        }

        self::$resolved = true;
        self::loadSiteConfig($site);
    }

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

    public static function get(): ?Site
    {
        return self::$currentSite;
    }

    public static function getId(): ?int
    {
        return self::$currentSite?->id;
    }

    public static function getConfig(?string $key = null)
    {
        if ($key === null) {
            return self::$config;
        }

        return self::$config[$key] ?? null;
    }

    public static function getSetting(string $key, $default = null)
    {
        return self::$config['settings'][$key] ?? $default;
    }

    public static function getTheme(): string
    {
        return self::$config['theme'] ?? 'default';
    }

    public static function isResolved(): bool
    {
        return self::$resolved;
    }

    public static function clear(): void
    {
        self::$currentSite = null;
        self::$resolved = false;
        self::$config = [];
    }

    public static function getUrl(): string
    {
        if (!self::$currentSite) {
            return config('app.url', '');
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $host = strtok($host, ':');

        if (self::$currentSite->domain && !self::isLocalhost($host)) {
            return "{$protocol}://" . self::$currentSite->domain;
        }

        if (self::$currentSite->subdomain) {
            $baseDomain = config('app.base_domain', 'example.com');
            return "{$protocol}://" . self::$currentSite->subdomain . '.' . $baseDomain;
        }

        if (self::isLocalhost($host)) {
            $port = $_SERVER['SERVER_PORT'] ?? '80';
            $portString = ($port != '80' && $port != '443') ? ":{$port}" : '';
            return "{$protocol}://{$host}{$portString}/" . self::$currentSite->slug;
        }

        return "{$protocol}://{$host}";
    }

    public static function url(string $path = ''): string
    {
        $baseUrl = self::getUrl();
        $path = ltrim($path, '/');
        return $baseUrl . ($path ? '/' . $path : '');
    }

    public static function asset(string $path): string
    {
        $siteSlug = self::$currentSite?->slug ?? 'default';
        $path = ltrim($path, '/');
        return "/assets/sites/{$siteSlug}/{$path}";
    }

    public static function slug(): string
    {
        return self::$currentSite?->slug ?? 'default';
    }

    public static function css(): string
    {
        $theme = self::getTheme();
        return "/themes/{$theme}.css";
    }

    public static function logo(): ?string
    {
        return self::$config['logo'] ?? null;
    }

    public static function favicon(): ?string
    {
        return self::$config['favicon'] ?? null;
    }

    public static function name(): string
    {
        return self::$config['site_name'] ?? 'My Site';
    }

    public static function isSite(int $siteId): bool
    {
        return self::getId() === $siteId;
    }

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

    public static function getAllSites(): array
    {
        return Site::where('is_active', 1)->get()->toArray();
    }
}
