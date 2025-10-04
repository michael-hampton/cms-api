<?php

namespace App\Models;

class PageSeo extends Model
{
    protected $table = 'page_seo';
    protected $fillable = [
        'page_id', 'meta_keywords', 'canonical_url', 'no_index', 'no_follow',
        'og_title', 'og_description', 'og_image', 'twitter_card', 'schema_markup',
        'created_at', 'updated_at', 'meta_description', 'meta_title'
    ];

    protected $casts = [
        'no_index' => 'boolean',
        'no_follow' => 'boolean',
        'schema_markup' => 'json'
    ];

    public function page(): ?Model
    {
        return $this->belongsTo(Page::class, 'page_id', 'id');
    }

    public function shouldNoIndex(): bool
    {
        return (bool) $this->no_index;
    }

    public function shouldNoFollow(): bool
    {
        return (bool) $this->no_follow;
    }

    public function getMetaKeywordsArray(): array
    {
        if (!$this->meta_keywords) {
            return [];
        }
        return array_map('trim', explode(',', $this->meta_keywords));
    }
}