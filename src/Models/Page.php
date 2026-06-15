<?php

namespace App\Models;

use App\Enums\OpenCollab\PageVisibility;
use App\Enums\Pages\PageStatus;
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
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'rejection_notes',
        'submitted_at',
        'resubmission_count',
        'created_by',
        'updated_by',
        'zones',
        'scheduled_at',
        'owner_id',
        'brief_id',
        'is_paid',
        'price',
        'contributor_id',
        'is_public_contribution',
        'premium_requested_at',
        'premium_requested_by',
        'premium_suggested_price',
        'premium_request_note',
        'premium_approved_at',
        'premium_approved_by',
        'premium_approval_note',
        'premium_rejected_at',
        'premium_rejected_by',
        'premium_rejection_reason',
        'monetisation_disabled_at',
        'monetisation_disabled_by',
        'monetisation_disabled_reason',
        'first_editorial_change_reported_at',
        'first_editorial_change_reported_by',
        'first_editorial_change_history_id',
        'moderation_notes'
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

    protected $hidden = [];
    protected $visible = [];

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
        'rejected_at' => 'datetime',
        'submitted_at' => 'datetime',
        'zones' => 'json',
        'is_paid' => 'boolean',
        'is_public_contribution' => 'boolean',
        'premium_requested_at' => 'datetime',
        'premium_requested_by' => 'integer',
        'premium_suggested_price' => 'integer',
        'premium_approved_at' => 'datetime',
        'premium_approved_by' => 'integer',
        'premium_rejected_at' => 'datetime',
        'premium_rejected_by' => 'integer',
        'monetisation_disabled_at' => 'datetime',
        'first_editorial_change_reported_at' => 'datetime',
        'first_editorial_change_reported_by' => 'integer',
        'first_editorial_change_history_id' => 'integer',
    ];

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
            PageStatus::REJECTED->value,
            PageStatus::INTERNAL->value,
        ];
    }

    public function blocks(bool $relation = false)
    {
        return $this->hasMany(Block::class, 'page_id', 'id', $relation)->orderBy('order');
    }

    public function widgets(bool $relation = false)
    {
        return $this->hasMany(PageWidget::class, 'page_id', 'id', $relation)
            ->orderBy('region')
            ->orderBy('priority');
    }

    public function comments(bool $relation = false)
    {
        return $this->hasMany(Comment::class, 'page_id', 'id', $relation);
    }

    public function categories(bool $relation = false)
    {
        return $this->belongsToMany(Category::class, 'page_categories', 'page_id', 'category_id', $relation);
    }

    public function tags(bool $relation = false)
    {
        return $this->belongsToMany(Tag::class, 'page_tags', 'page_id', 'tag_id', $relation);
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
        return $query->where('status', PageStatus::PUBLISHED->value);
    }

    public function scopeVisibleTo(QueryBuilder $query, ?AuthenticatedMember $member): QueryBuilder
    {
        if (!$member) {
            return $query->where('visibility', PageVisibility::PUBLIC->value);
        }

        return $query;
    }
}
