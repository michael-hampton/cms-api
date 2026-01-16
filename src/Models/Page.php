<?php

namespace App\Models;

use App\Enums\PageStatus;
use App\Framework\Authorization\AuthenticatedMember;
use App\Framework\Database\QueryBuilder;
use App\Framework\Database\Relations\HasManyHandler;
use App\Framework\Support\Collection;
use App\Models\Concerns\HasCloneHistory;
use App\Models\Concerns\TracksCreator;

class Page extends Model
{
    use HasCloneHistory, TracksCreator;

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
        'gallery_slides',
        'clone_history',
        'requires_approval',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
        'zones',
        'scheduled_at'
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
        'clone_history' => 'array',
        'requires_approval' => 'boolean',
        'approved_at' => 'datetime',
        'zones' => 'json',
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

    // Add these methods to App\Models\Page.php

    public function views($relation = false)
    {
        return $this->hasMany(PageView::class, 'page_id', 'id', $relation);
    }

    public function likes($relation = false)
    {
        return $this->hasMany(PageLike::class, 'page_id', 'id', $relation);
    }

    public function likedByMembers($relation = false)
    {
        return $this->belongsToMany(
            Member::class,
            'page_likes',
            'page_id',
            'member_id',
            $relation
        )->withPivot('liked_at');
    }

    public function getLikeCount(): int
    {
        return PageLike::getLikeCount($this->id);
    }

    public function getTotalViewCount(): int
    {
        return PageView::getTotalViewCount($this->id);
    }

    public function isLikedBy(?int $memberId, int $siteId): bool
    {
        if (!$memberId) {
            return false;
        }

        return PageLike::isLikedBy($this->id, $memberId, $siteId);
    }

    public function products(bool $relation = false)
    {
        return $this->belongsToMany(
            Product::class,
            'page_products',
            'page_id',
            'product_id',
            $relation
        );
    }

    /**
     * Check if page requires approval before publishing
     */
    public function requiresApproval(): bool
    {
        return $this->requires_approval === true;
    }

    /**
     * Check if page is approved
     */
    public function isApproved(): bool
    {
        return $this->approved_by !== null && $this->approved_at !== null;
    }

    /**
     * Check if page is waiting for approval
     */
    public function isWaitingApproval(): bool
    {
        return $this->status === PageStatus::WAITING_APPROVAL->value;
    }

    /**
     * Check if page is private
     */
    public function isPrivate(): bool
    {
        return $this->status === PageStatus::PRIVATE->value;
    }

    /**
     * Check if page is on hold
     */
    public function isOnHold(): bool
    {
        return $this->status === PageStatus::ON_HOLD->value;;
    }

    /**
     * Check if status can be changed to target status
     */
    public function canTransitionTo(PageStatus|string $targetStatus): bool
    {
        if (is_string($targetStatus)) {
            $targetStatus = PageStatus::from($targetStatus);
        }

        $allowedTransitions = [
            PageStatus::DRAFT->value => [
                PageStatus::PUBLISHED,
                PageStatus::WAITING_APPROVAL,
                PageStatus::PRIVATE,
                PageStatus::ON_HOLD,
                PageStatus::ARCHIVED,
                PageStatus::INTERNAL
            ],
            PageStatus::WAITING_APPROVAL->value => [
                PageStatus::PUBLISHED, // Only after approval
                PageStatus::DRAFT,
                PageStatus::ON_HOLD,
                PageStatus::ARCHIVED
            ],
            PageStatus::PUBLISHED->value => [
                PageStatus::DRAFT,
                PageStatus::PRIVATE,
                PageStatus::ON_HOLD,
                PageStatus::ARCHIVED
            ],
            PageStatus::PRIVATE->value => [
                PageStatus::DRAFT,
                PageStatus::PUBLISHED,
                PageStatus::WAITING_APPROVAL,
                PageStatus::ON_HOLD,
                PageStatus::ARCHIVED
            ],
            PageStatus::ON_HOLD->value => [
                PageStatus::DRAFT,
                PageStatus::WAITING_APPROVAL,
                PageStatus::PUBLISHED,
                PageStatus::PRIVATE,
                PageStatus::ARCHIVED
            ],
            PageStatus::INTERNAL->value => [  // NEW
                PageStatus::DRAFT,
                PageStatus::WAITING_APPROVAL,
                PageStatus::PUBLISHED,
                PageStatus::PRIVATE,
                PageStatus::ON_HOLD,
                PageStatus::ARCHIVED
            ],
            PageStatus::ARCHIVED->value => [
                PageStatus::DRAFT,
                PageStatus::ON_HOLD
            ],
            PageStatus::SCHEDULED->value => [
                PageStatus::PUBLISHED,
                PageStatus::DRAFT,
                PageStatus::ARCHIVED
            ]
        ];

        $currentStatusValue = $this->status instanceof PageStatus
            ? $this->status->value
            : $this->status;

        return in_array($targetStatus, $allowedTransitions[$currentStatusValue] ?? []);
    }

    /**
     * Approve page for publishing
     */
    public function approve(int $userId): void
    {
        $this->approved_by = $userId;
        $this->approved_at = date('Y-m-d H:i:s');
        $this->save();
    }

    /**
     * Remove approval
     */
    public function removeApproval(): void
    {
        $this->approved_by = null;
        $this->approved_at = null;
        $this->save();
    }

    /**
     * Relationship to user who approved
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /**
     * Get all valid statuses
     */
    public static function getValidStatuses(): array
    {
        return [
            PageStatus::DRAFT->value,
            PageStatus::PUBLISHED->value,
            PageStatus::ARCHIVED->value,
            PageStatus::SCHEDULED->value,
            PageStatus::WAITING_APPROVAL->value,
            PageStatus::PRIVATE->value,
            PageStatus::ON_HOLD->value,
            PageStatus::INTERNAL->value,
        ];
    }

    public function isInternal(): bool
    {
        return $this->status === PageStatus::INTERNAL->value;
    }
}