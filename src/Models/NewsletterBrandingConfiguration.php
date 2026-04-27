<?php

namespace App\Models;

use App\Framework\Database\QueryBuilder;
use App\Models\Concerns\HasCloneHistory;

/**
 * Represents both:
 *   - Per-newsletter branding configurations  (type = 'newsletter', newsletter_id set)
 *   - Site-level reusable email themes        (type = 'email_template', site_id set, newsletter_id null)
 *
 * theme_json structure:
 * {
 *   "colors":   { "primary": "#667eea", ... },
 *   "fonts":    { "body": { "family": "...", "size": "15px", "weight": "400" }, ... },
 *   "assets":   { "logo": { "type": "image", "url": "...", "alt": "...", "width": 200, "height": 50 }, ... },
 *   "settings": { "max_width": 600, "padding": 20, "border_radius": 8, "show_footer": true, ... }
 * }
 *
 * @property int $id
 * @property int|null $newsletter_id
 * @property int|null $site_id
 * @property string|null $name
 * @property string|null $slug
 * @property string|null $description
 * @property bool $is_active
 * @property bool $is_default
 * @property string $type            email_template | newsletter
 * @property array|null $clone_history
 * @property string|null $logo_url
 * @property string|null $header_text
 * @property string|null $footer_text
 * @property array|null $theme_json
 * @property string|null $custom_css
 * @property string $created_at
 * @property string $updated_at
 */
class NewsletterBrandingConfiguration extends Model
{
    use HasCloneHistory;

    public const TYPE_EMAIL_TEMPLATE = 'email_template';
    public const TYPE_NEWSLETTER = 'newsletter';

    protected $table = 'newsletter_branding_configurations';

    protected $fillable = [
        'newsletter_id',
        'site_id',
        'name',
        'slug',
        'description',
        'is_active',
        'is_default',
        'type',
        'clone_history',
        'logo_url',
        'header_text',
        'footer_text',
        'theme_json',
        'custom_css',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'theme_json' => 'array',
        'clone_history' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function newsletter()
    {
        return $this->belongsTo(Newsletter::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeEmailTemplates(QueryBuilder $query): QueryBuilder
    {
        return $query->where('type', self::TYPE_EMAIL_TEMPLATE);
    }

    public function scopeNewsletterType(QueryBuilder $query): QueryBuilder
    {
        return $query->where('type', self::TYPE_NEWSLETTER);
    }

    public function scopeActive(QueryBuilder $query): QueryBuilder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault(QueryBuilder $query): QueryBuilder
    {
        return $query->where('is_default', true);
    }

    public function scopeBySite(QueryBuilder $query, int $siteId): QueryBuilder
    {
        return $query->where('site_id', $siteId);
    }

    public function scopeBySlug(QueryBuilder $query, string $slug): QueryBuilder
    {
        return $query->where('slug', $slug);
    }

    // ── theme_json accessors ──────────────────────────────────────────────────

    public function getColor(string $key, string $default = '#000000'): string
    {
        return $this->theme_json['colors'][$key] ?? $default;
    }

    public function getColors(): array
    {
        return $this->theme_json['colors'] ?? [];
    }

    public function getFont(string $key): ?array
    {
        return $this->theme_json['fonts'][$key] ?? null;
    }

    public function getFonts(): array
    {
        return $this->theme_json['fonts'] ?? [];
    }

    public function getAsset(string $key): ?array
    {
        return $this->theme_json['assets'][$key] ?? null;
    }

    public function getAssets(): array
    {
        return $this->theme_json['assets'] ?? [];
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->theme_json['settings'][$key] ?? $default;
    }

    public function getSettings(): array
    {
        return $this->theme_json['settings'] ?? [];
    }

    // ── Branding snapshot (used by versioning) ────────────────────────────────

    public function toSnapshot(): array
    {
        return [
            'logo_url' => $this->logo_url,
            'header_text' => $this->header_text,
            'footer_text' => $this->footer_text,
            'theme_json' => $this->theme_json,
            'custom_css' => $this->custom_css,
        ];
    }

    // ── Versioning ────────────────────────────────────────────────────────────

    public function latestVersion(): ?NewsletterBrandingVersion
    {
        return NewsletterBrandingVersion::where('branding_config_id', $this->id)
            ->orderBy('version_number', 'desc')
            ->first();
    }
}