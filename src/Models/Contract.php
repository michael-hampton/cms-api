<?php

namespace App\Models;

use App\Enums\OpenCollab\ContractStatus;

/**
 * Versioned contributor contract for a site.
 *
 * Lifecycle: draft → published → archived
 *
 * Published contracts are immutable. Any change after publish requires
 * creating a new version via ContractService::cloneToDraft().
 *
 * Columns added by migration 2024_xx_add_status_to_contracts:
 *   status          enum('draft','published','archived')  default 'draft'
 *   published_at    timestamp nullable
 *   published_by    unsignedBigInteger nullable (FK → users)
 *   archived_at     timestamp nullable
 *   archived_by     unsignedBigInteger nullable (FK → users)
 *   source_template_id       unsignedBigInteger nullable (FK → oc_contract_templates)
 *   cloned_from_version_id   unsignedBigInteger nullable (FK → oc_contracts self-ref)
 */
class Contract extends Model
{
    protected $table = 'oc_contracts';

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
        'issued_by_user_id',
        'issued_at',
        'archived_at',
        'archived_by',
        'source_template_id',
        'cloned_from_version_id',
    ];

    protected $casts = [
        'version' => 'integer',
        'template_id' => 'integer',
        'document_id' => 'integer',
        'source_document_id' => 'integer',
        'status' => ContractStatus::class,
        'published_at' => 'datetime',
        'issued_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    // ── Accessors ────────────────────────────────────────────────────────────

    public function isPublished(): bool
    {
        return $this->status === ContractStatus::Published;
    }

    public function isDraft(): bool
    {
        return $this->status === ContractStatus::Draft;
    }

    public function isArchived(): bool
    {
        return $this->status === ContractStatus::Archived;
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function signatures($relation = false)
    {
        return $this->hasMany(UserContractSignature::class, 'contract_id', 'id', $relation);
    }

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
        return $this->belongsTo(ContractTemplate::class, 'source_template_id', 'id', $relation);
    }

    public function clonedFrom($relation = false)
    {
        return $this->belongsTo(self::class, 'cloned_from_version_id', 'id', $relation);
    }
}
