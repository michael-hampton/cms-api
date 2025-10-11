<?php

namespace App\Models;

use App\Framework\Database\QueryBuilder;
use App\Framework\Database\Relations\BelongsToManyHandler;
use App\Framework\Database\Relations\HasManyHandler;
use App\Framework\Database\Relations\RelationBuilder;
use App\Framework\Support\Collection;

class Page extends Model
{
    protected $table = 'pages';

    protected $fillable = [
        'title', 'slug', 'content', 'status', 'meta_title', 'meta_description',
        'published_at', 'created_at', 'updated_at', 'page_type', 'custom_handler', 'author_id', 'description', 'site_id', 'deleted_at'
    ];

    // Relations that should always be included in serialization
    protected $alwaysInclude = [
        'blocks', 'categories', 'tags', 'metadata', 'seo',
        'settings', 'social', 'customFields'
    ];

    // You can hide sensitive attributes
    protected $hidden = [];

    // Or specify only visible attributes
    protected $visible = [];

    // Configure automatic JSON casting for data field
    protected $casts = [
        'published_at' => 'date',
        'created_at' => 'date',
        'updated_at' => 'date'
    ];

    public function blocks(bool $relation = false)
    {
        return $this->hasMany(Block::class, 'page_id', 'id', $relation)->orderBy('order');
    }

    public function categories(bool $relation = false)
    {
        return $this->belongsToMany(
            Category::class,
            'page_categories',
            'page_id',
            'category_id',
            $relation
        );
    }

    public function tags(bool $relation = false)
    {
        return $this->belongsToMany(
            Tag::class,
            'page_tags',
            'page_id',
            'tag_id',
            $relation
        );
    }

    public function metadata(): ?Model
    {
        return $this->hasOne(PageMetadata::class, 'page_id', 'id');
    }

    public function seo(): ?Model
    {
        return $this->hasOne(PageSeo::class, 'page_id', 'id');
    }

    public function settings(): ?Model
    {
        return $this->hasOne(PageSettings::class, 'page_id', 'id');
    }

    public function social(): ?Model
    {
        return $this->hasOne(PageSocial::class, 'page_id', 'id');
    }

    public function customFields($relation = false): HasManyHandler|Collection
    {
        return $this->hasMany(PageCustomField::class, 'page_id', 'id', $relation);
    }

    public function accessRoles(): Collection
    {
        return $this->belongsToMany(PageAccessRole::class);
    }

    public function getUrlAttribute(): string
    {
        return '/' . $this->slug;
        //return '/pages/' . $this->slug;
    }

    public function author($relation = false)
    {
        return $this->belongsTo(Author::class, 'author_id', 'id', $relation);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function getPublishedAtAttribute()
    {
        $rawData = $this->attributes['published_at'] ?? null;
        return $rawData ? date('Y-m-d H:i:s', strtotime($rawData)) : null;
    }

    public function scopePublished(QueryBuilder $query): QueryBuilder
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft(QueryBuilder $query): QueryBuilder
    {
        return $query->where('status', 'draft');
    }

    public function scopeArchived(QueryBuilder $query): QueryBuilder
    {
        return $query->where('status', 'archived');
    }

    public function scopeBySlug(QueryBuilder $query, string $slug): QueryBuilder
    {
        return $query->where('slug', $slug);
    }

    public function scopeByStatus(QueryBuilder $query, string $status): QueryBuilder
    {
        return $query->where('status', $status);
    }

    public function scopeFeatured(QueryBuilder $query): QueryBuilder
    {
        return $query->join('page_metadata', 'pages.id', '=', 'page_metadata.page_id')
            ->where('page_metadata.featured', 1);
    }

    public function toArrayWithRelations(): array
    {
        $data = $this->toArray();

        // Load relationships if not already loaded
        if (!$this->relationLoaded('categories')) {
            $this->load(['categories', 'tags', 'metadata', 'seo', 'settings', 'social', 'customFields']);
        }

        return array_merge($data, [
            'categories' => $this->categories ? $this->categories->toArray() : [],
            'tags' => $this->tags ? $this->tags->toArray() : [],
            'metadata' => $this->metadata ? $this->metadata->toArray() : null,
            'seo' => $this->seo ? $this->seo->toArray() : null,
            'settings' => $this->settings ? $this->settings->toArray() : null,
            'social' => $this->social ? $this->social->toArray() : null,
            'custom_fields' => $this->customFields ? $this->customFields->toArray() : []
        ]);
    }

    public function syncCustomFields(array $fields, int $siteId): void
    {
        // Normalize fields: convert scalars to ['default_value' => value]
        $fields = array_map(fn($item) => is_array($item) ? $item : ['default_value' => $item], $fields);

        // Fire syncing event
        $this->fireModelEvent('syncingCustomFields');

        // Collect all field IDs from payload
        $incomingFieldIds = array_keys($fields);

        // Remove old PageCustomField values not in the new payload
        $query = PageCustomField::where('page_id', $this->id);

        if (!empty($incomingFieldIds)) {
            $query->whereNotIn('custom_field_definition_id', $incomingFieldIds);
        }

        $query->delete();

        // Update or create new PageCustomField values
        foreach ($fields as $fieldId => $fieldData) {
            $value = $fieldData['default_value'] ?? null;

            // Ensure the field definition exists
            $definition = CustomFieldDefinition::find($fieldId);
            if (!$definition) {
                continue;
            }

            PageCustomField::updateOrCreate(
                [
                    'page_id' => $this->id,
                    'custom_field_definition_id' => $definition->id,
                ],
                [
                    'field_value' => $value, // make sure this matches your DB column
                    'site_id' => $siteId,
                ]
            );
        }

        // Fire synced event
        $this->fireModelEvent('syncedCustomFields');
    }



}