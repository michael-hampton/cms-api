<?php

namespace App\Models;

use App\Framework\Authorization\AuthenticatedMember;
use App\Framework\Database\QueryBuilder;
use App\Framework\Database\Relations\BelongsToManyHandler;
use App\Framework\Database\Relations\HasManyHandler;
use App\Framework\Database\Relations\RelationBuilder;
use App\Framework\Support\Collection;

class Page extends Model
{
    protected $table = 'pages';

    protected $fillable = [
        'title',
        'slug',
        'content',
        'status',
        'meta_title',
        'meta_description',
        'published_at',
        'created_at',
        'updated_at',
        'page_type',
        'custom_handler',
        'author_id',
        'description',
        'site_id',
        'deleted_at',
        'territory_id',
        'region_set_id',
        'subtitle',
        'listing_synopsis',
        'listing_title',
        'listing_label',
        'listing_image_id',
        'listing_use_as_hero',
        'hero_type',
        'hero_image_id',
        'hero_video_url',
        'crop_overrides',
        'resolved_images',
    ];

    protected $alwaysInclude = [
        'blocks',
        'categories',
        'tags',
        'metadata',
        'seo',
        'settings',
        'social',
        'customFields',
        'authors',
        'pageAuthors',
        'regionSets',
        'territories'
    ];

    // You can hide sensitive attributes
    protected $hidden = [];

    // Or specify only visible attributes
    protected $visible = [];

    // Configure automatic JSON casting for data field
    protected $casts = [
        'published_at' => 'date',
        'created_at' => 'date',
        'updated_at' => 'date',
        'crop_overrides' => 'json',
        'resolved_images' => 'json',
        'listing_use_as_hero' => 'boolean',
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

    public function pageAuthors($relation = false)
    {
        return $this->hasMany(PageAuthor::class, 'page_id', 'id', $relation)
            ->orderBy('sort_order');
    }

    public function authors($relation = true)
    {
        return $this->belongsToMany(
            Author::class,
            'page_authors',
            'page_id',
            'author_id',
            true
        )->withPivot('role', 'sort_order')
            ->orderBy('page_authors.sort_order')->get();
    }

    public function primaryAuthors($relation = false)
    {
        return $this->belongsToMany(
            Author::class,
            'page_authors',
            'page_id',
            'author_id',
            true
        )->wherePivot('role', 'primary')
            ->withPivot('sort_order')
            ->orderBy('page_authors.sort_order');
    }

    public function contributors($relation = false)
    {
        return $this->belongsToMany(
            Author::class,
            'page_authors',
            'page_id',
            'author_id',
            true
        )->wherePivot('role', 'contributor')
            ->withPivot('sort_order')
            ->orderBy('page_authors.sort_order');
    }

// Helper method to get primary author (for backward compatibility)
    public function getPrimaryAuthor()
    {
        return $this->primaryAuthors()->first();
    }

    public function regionSets(bool $relation = false)
    {
        return $this->belongsToMany(
            RegionSet::class,
            'page_region_sets',
            'page_id',
            'region_set_id',
            $relation
        );
    }

    public function territories(bool $relation = false)
    {
        return $this->belongsToMany(
            Territory::class,
            'page_territories',
            'page_id',
            'territory_id',
            $relation
        );
    }

// Add to App/Models/Page.php

    public function requiresMemberLogin(): bool
    {
        return (bool) ($this->requires_member_login ?? false);
    }

    public function getAllowedMemberRoles(): ?array
    {
        if (!$this->allowed_member_roles) {
            return null;
        }

        $roles = json_decode($this->allowed_member_roles, true);
        return is_array($roles) ? $roles : null;
    }

    public function canBeAccessedBy(?AuthenticatedMember $member): bool
    {
        if (!$this->requiresMemberLogin()) {
            return true;
        }

        if (!$member) {
            return false;
        }

        $allowedRoles = $this->getAllowedMemberRoles();

        if (!$allowedRoles) {
            return true; // Any authenticated member
        }

        return $member->hasAnyRole($allowedRoles);
    }
}