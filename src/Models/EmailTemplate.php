<?php

namespace App\Models;

use App\Framework\Database\QueryBuilder;

class EmailTemplate extends Model
{
    protected $table = 'email_templates';

    protected $fillable = [
        'site_id',
        'theme_id',
        'name',
        'slug',
        'description',
        'category',
        'blocks',
        'is_active',
        'thumbnail_url',
    ];

    protected $casts = [
        'blocks' => 'array',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function theme()
    {
        return $this->belongsTo(EmailTheme::class, 'theme_id');
    }

    // ── Accessors ─────────────────────────────────────────────

    /**
     * Return only visible blocks for rendering.
     */
    public function getVisibleBlocks(): array
    {
        $blocks = $this->blocks ?? [];
        return array_values(array_filter($blocks, fn($b) => ($b['visible'] ?? true) === true));
    }

    // ── Scopes ────────────────────────────────────────────────

    public function scopeActive(QueryBuilder $query): QueryBuilder
    {
        return $query->where('is_active', true);
    }

    public function scopeBySite(QueryBuilder $query, int $siteId): QueryBuilder
    {
        return $query->where('site_id', $siteId);
    }

    public function scopeByCategory(QueryBuilder $query, string $category): QueryBuilder
    {
        return $query->where('category', $category);
    }

    public function scopeBySlug(QueryBuilder $query, string $slug): QueryBuilder
    {
        return $query->where('slug', $slug);
    }
}