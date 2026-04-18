<?php

namespace App\Models;


class SegmentRule extends Model
{
    protected $table = 'segment_rules';
    protected $fillable = [
        'segment_id',
        'field',
        'operator',
        'value',
        'boolean',
        'sort_order',
    ];

    protected $casts = [
        'operator' => 'string',
        'boolean' => 'string',
        'value' => 'json',
    ];

    public function segment()
    {
        return $this->belongsTo(Segment::class);
    }
}