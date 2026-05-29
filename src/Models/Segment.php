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
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'priority'     => 'integer',
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

    /**
     * Filter segments to a specific subject type.
     */
    public function scopeForSubject($query, SegmentSubjectType $subjectType): void
    {
        $query->where('subject_type', $subjectType->value);
    }
}