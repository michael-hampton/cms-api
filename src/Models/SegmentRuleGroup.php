<?php

namespace App\Models;

use App\Enums\Member\SegmentRuleBoolean;

class SegmentRuleGroup extends Model
{
    protected $table = 'segment_rule_groups';

    protected $fillable = [
        'segment_id',
        'parent_id',
        'boolean',
        'sort_order',
    ];

    protected $casts = [
        'boolean'    => SegmentRuleBoolean::class,
        'sort_order' => 'integer',
    ];

    public function segment()
    {
        return $this->belongsTo(Segment::class);
    }

    public function parent()
    {
        return $this->belongsTo(SegmentRuleGroup::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(SegmentRuleGroup::class, 'parent_id')->orderBy('sort_order');
    }

    public function rules()
    {
        return $this->hasMany(SegmentRule::class, 'group_id')->orderBy('sort_order');
    }
}