<?php

namespace App\Models;

class BriefRelationship extends Model
{
    protected $table = 'brief_relationships';

    protected $fillable = [
        'brief_id', 'related_brief_id', 'related_page_id',
        'relationship_type', 'sort_order'
    ];

    public function brief()
    {
        return $this->belongsTo(Brief::class);
    }

    public function relatedBrief()
    {
        return $this->belongsTo(Brief::class, 'related_brief_id');
    }

    public function relatedPage()
    {
        return $this->belongsTo(Page::class, 'related_page_id');
    }
}