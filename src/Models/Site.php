<?php

namespace App\Models;

class Site extends Model
{
    protected $table = 'sites';

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'subdomain',
        'theme',
        'logo',
        'favicon',
        'is_active',
        'is_default',
        'settings'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'settings' => 'json',
        'created_at' => 'date',
        'updated_at' => 'date'
    ];

    public static function resolveSite(string $siteSlug): int
    {
        return Site::where('slug', $siteSlug)->first()->id ?? config('app.default_site_id', 1);
    }

    /**
     * Get pages for this site
     */
    public function pages()
    {
        return $this->hasMany(Page::class, 'site_id', 'id');
    }

    /**
     * Get menus for this site
     */
    public function menus()
    {
        return $this->hasMany(Menu::class, 'site_id', 'id');
    }

    /**
     * Get categories for this site
     */
    public function categories()
    {
        return $this->hasMany(Category::class, 'site_id', 'id');
    }

    /**
     * Get tags for this site
     */
    public function tags()
    {
        return $this->hasMany(Tag::class, 'site_id', 'id');
    }

    /**
     * Get custom field definitions for this site
     */
    public function customFieldDefinitions()
    {
        return $this->hasMany(CustomFieldDefinition::class, 'site_id', 'id');
    }

    /**
     * Get site URL
     */
    public function getUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        if ($this->domain) {
            return "{$protocol}://{$this->domain}";
        }

        if ($this->subdomain) {
            $baseDomain = config('app.base_domain', 'example.com');
            return "{$protocol}://{$this->subdomain}.{$baseDomain}";
        }

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "{$protocol}://{$host}/{$this->slug}";
    }

    public function getThemePath(): string
    {
        return $this->theme ?? 'default';
    }

    /**
     * Get a specific setting
     */
    public function getSetting(string $key, $default = null)
    {
        $settings = is_string($this->settings) ? json_decode($this->settings, true) : $this->settings;
        return $settings[$key] ?? $default;
    }

    /**
     * Set a specific setting
     */
    public function setSetting(string $key, $value): void
    {
        $settings = is_string($this->settings) ? json_decode($this->settings, true) : ($this->settings ?? []);
        $settings[$key] = $value;
        $this->settings = $settings;
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Check if site is default
     */
    public function isDefault(): bool
    {
        return (bool) $this->is_default;
    }

    /**
     * Check if site is accessible via given domain
     */
    public function matchesDomain(string $domain): bool
    {
        // Remove port
        $domain = strtok($domain, ':');

        // Direct domain match
        if ($this->domain === $domain) {
            return true;
        }

        // Subdomain match
        if ($this->subdomain) {
            $baseDomain = config('app.base_domain', 'example.com');
            $fullSubdomain = "{$this->subdomain}.{$baseDomain}";
            if ($fullSubdomain === $domain) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scope: active sites only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Scope: get default site
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', 1);
    }
}