<?php

namespace App\Models;

use App\Enums\OpenCollab\TermsVersionStatus;

class TermsVersion extends Model
{
    protected $table = 'oc_terms_versions';

    protected $fillable = [
        'site_id',
        'semantic_version',
        'title',
        'source_format',
        'source_content',
        'rendered_format',
        'rendered_content',
        'rendered_hash',
        'status',
        'is_material_change',
        'change_summary',
        'document_id',
        'source_document_id',
        'source_type',
        'extraction_status',
        'extraction_error',
        'supersedes_terms_version_id',
        'published_at',
        'published_by_user_id',
        'archived_at',
        'archived_by_user_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'site_id' => 'integer',
        'document_id' => 'integer',
        'source_document_id' => 'integer',
        'supersedes_terms_version_id' => 'integer',
        'is_material_change' => 'boolean',
        'status' => TermsVersionStatus::class,
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function isDraft(): bool
    {
        return $this->status === TermsVersionStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status === TermsVersionStatus::Published;
    }

    public function isArchived(): bool
    {
        return $this->status === TermsVersionStatus::Archived;
    }
}
