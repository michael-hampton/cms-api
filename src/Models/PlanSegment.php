<?php

namespace App\Models;

class PlanSegment extends Model
{
    protected $table = 'plan_segment';

    protected $fillable = [
        'plan_id',
        'segment_id',
        'priority',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'priority'   => 'integer',
        'starts_at'  => 'datetime',
        'ends_at'    => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function segment()
    {
        return $this->belongsTo(Segment::class);
    }
}