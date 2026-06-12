<?php

namespace App\Models;

/**
 * Reusable drafting source for brand/editorial guidelines.
 *
 * Templates create draft guideline versions only.
 * Published guidelines remain immutable snapshots regardless of template changes.
 *
 * Table: oc_guideline_templates
 */
class GuidelineTemplate extends Model
{
    protected $table = 'oc_guideline_templates';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'content',
        'source_document_id',
        'source_type',
        'content_format',
        'extraction_status',
        'extraction_error',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'source_document_id' => 'integer',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    public function creator($relation = false)
    {
        return $this->belongsTo(User::class, 'created_by', 'id', $relation);
    }

    public function updater($relation = false)
    {
        return $this->belongsTo(User::class, 'updated_by', 'id', $relation);
    }

    public function guidelines($relation = false)
    {
        return $this->hasMany(Guideline::class, 'source_template_id', 'id', $relation);
    }
}
