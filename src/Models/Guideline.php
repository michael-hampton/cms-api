<?php

namespace App\Models;

use App\Enums\OpenCollab\GuidelineStatus;

/**
 * Versioned brand/editorial guidelines for a site.
 *
 * Lifecycle: draft → published → archived
 *
 * Published guidelines are immutable. Changes require a new version via
 * GuidelineService::cloneToDraft().
 *
 * Columns added by migration 2024_xx_add_status_to_guidelines:
 *   status          enum('draft','published','archived')  default 'draft'
 *   published_at    timestamp nullable
 *   published_by    unsignedBigInteger nullable (FK → users)
 *   archived_at     timestamp nullable
 *   archived_by     unsignedBigInteger nullable (FK → users)
 *   source_template_id       unsignedBigInteger nullable (FK → oc_guideline_templates)
 *   cloned_from_version_id   unsignedBigInteger nullable (FK → oc_guidelines self-ref)
 */
class Guideline extends Model
{
    protected $table = 'oc_guidelines';

    protected $fillable = [
        'site_id',
        'title',
        'template_id',
        'document_id',
        'source_document_id',
        'source_type',
        'version',
        'content',
        'content_format',
        'extraction_status',
        'extraction_error',
        'status',
        'published_at',
        'published_by',
        'published_by_user_id',
        'archived_at',
        'archived_by',
        'source_template_id',
        'cloned_from_version_id',
    ];

    protected $casts = [
        'site_id' => 'int',
        'version' => 'int',
        'template_id' => 'int',
        'document_id' => 'int',
        'source_document_id' => 'int',
        'status' => GuidelineStatus::class,
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    // ── Accessors ────────────────────────────────────────────────────────────

    public function isPublished(): bool
    {
        return $this->status === GuidelineStatus::Published;
    }

    public function isDraft(): bool
    {
        return $this->status === GuidelineStatus::Draft;
    }

    public function isArchived(): bool
    {
        return $this->status === GuidelineStatus::Archived;
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function publisher($relation = false)
    {
        return $this->belongsTo(User::class, 'published_by', 'id', $relation);
    }

    public function archiver($relation = false)
    {
        return $this->belongsTo(User::class, 'archived_by', 'id', $relation);
    }

    public function sourceTemplate($relation = false)
    {
        return $this->belongsTo(GuidelineTemplate::class, 'source_template_id', 'id', $relation);
    }

    public function clonedFrom($relation = false)
    {
        return $this->belongsTo(self::class, 'cloned_from_version_id', 'id', $relation);
    }
}
