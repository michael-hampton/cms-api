<?php

namespace App\Resources\OpenCollab;

use App\Models\TermsVersion;

class TermsVersionResource
{
    public function __construct(private readonly TermsVersion $terms)
    {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->terms->id,
            'site_id' => $this->terms->site_id,
            'semantic_version' => $this->terms->semantic_version,
            'title' => $this->terms->title,
            'source_format' => $this->terms->source_format,
            'source_content' => $this->terms->source_content,
            'rendered_format' => $this->terms->rendered_format,
            'rendered_content' => $this->terms->rendered_content,
            'rendered_hash' => $this->terms->rendered_hash,
            'status' => $this->terms->status,
            'is_material_change' => $this->terms->is_material_change,
            'change_summary' => $this->terms->change_summary,
            'document_id' => $this->terms->document_id,
            'source_document_id' => $this->terms->source_document_id,
            'source_type' => $this->terms->source_type,
            'extraction_status' => $this->terms->extraction_status,
            'extraction_error' => $this->terms->extraction_error,
            'supersedes_terms_version_id' => $this->terms->supersedes_terms_version_id,
            'published_at' => $this->terms->published_at,
            'published_by_user_id' => $this->terms->published_by_user_id,
            'archived_at' => $this->terms->archived_at,
            'archived_by_user_id' => $this->terms->archived_by_user_id,
            'created_by_user_id' => $this->terms->created_by_user_id,
            'created_at' => $this->terms->created_at,
            'updated_at' => $this->terms->updated_at,
        ];
    }
}
