<?php

namespace App\Models;

use App\Enums\Member\SegmentSubjectType;

class Segment extends Model
{
    protected $table = 'segments';

    protected $fillable = [
        'key',
        'name',
        'description',
        'category',
        'subject_type',
        'priority',
        'is_active',
        'last_recalculated_at',
    ];

    protected $casts = [
        'is_active'             => 'boolean',
        'priority'              => 'integer',
        'last_recalculated_at'  => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function rules()
    {
        return $this->hasMany(SegmentRule::class);
    }

    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }

    public function memberSegments()
    {
        return $this->hasMany(MemberSegment::class);
    }

    public function plans()
    {
        return $this->belongsToMany(SubscriptionPlan::class, 'plan_segment');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForSubject($query, SegmentSubjectType $subjectType): void
    {
        $query->where('subject_type', $subjectType->value);
    }
}