<?php

namespace App\Models;

class PageWidget extends Model
{
    protected $table = 'page_widgets';

    protected $fillable = [
        'page_id',
        'widget_key',
        'region',
        'priority',
        'is_enabled',
        'configuration',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'page_id' => 'integer',
        'priority' => 'integer',
        'is_enabled' => 'boolean',
        'configuration' => 'array',
    ];

    public function page(): ?Model
    {
        return $this->belongsTo(Page::class, 'page_id', 'id');
    }
}
