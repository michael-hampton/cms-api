<?php

namespace App\Models;

/**
 * Reusable drafting source for contributor contracts.
 *
 * Templates are NOT legally binding, signable, or compliance entities.
 * Published contracts copy a full content snapshot at creation time;
 * editing a template never mutates any existing contract.
 *
 * Table: oc_contract_templates
 */
class ContractTemplate extends Model
{
    protected $table = 'oc_contract_templates';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'content',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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

    public function contracts($relation = false)
    {
        return $this->hasMany(Contract::class, 'source_template_id', 'id', $relation);
    }
}