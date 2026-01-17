<?php

namespace App\Models;

class BriefAttachment extends Model
{
    protected $table = 'brief_attachments';

    protected $fillable = [
        'brief_id',
        'type',
        'image_id',
        'product_id',
        'file_url',
        'file_name',
        'url',
        'metadata',
        'sort_order'
    ];

    protected $casts = [
        'metadata' => 'json'
    ];

    public function brief(bool $relation = false)
    {
        return $this->belongsTo(Brief::class, 'brief_id', 'id', $relation);
    }

    public function image(bool $relation = false)
    {
        return $this->belongsTo(Image::class, 'image_id', 'id', $relation);
    }

    public function product(bool $relation = false)
    {
        return $this->belongsTo(Product::class, 'product_id', 'id', $relation);
    }
}