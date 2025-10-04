<?php

namespace App\Models;

use App\Framework\Database\QueryBuilder;

class ImageUsage extends Model
{
    protected $table = 'image_usage';

    protected $fillable = [
        'image_id', 'usable_type', 'usable_id', 'context', 'created_at'
    ];

    protected $casts = [
        'image_id' => 'integer',
        'usable_id' => 'integer',
        'created_at' => 'date'
    ];

    public function image(): ?Model
    {
        return $this->belongsTo(Image::class, 'image_id', 'id');
    }

    public function usable(): ?Model
    {
        switch ($this->usable_type) {
            case 'page':
                return $this->belongsTo(Page::class, 'usable_id', 'id');
            case 'block':
                return $this->belongsTo(Block::class, 'usable_id', 'id');
            default:
                return null;
        }
    }

    public function scopeByType(QueryBuilder $query, string $type): QueryBuilder
    {
        return $query->where('usable_type', $type);
    }

    public function scopeByUsable(QueryBuilder $query, string $type, int $id): QueryBuilder
    {
        return $query->where('usable_type', $type)->where('usable_id', $id);
    }

    public function scopeByContext(QueryBuilder $query, string $context): QueryBuilder
    {
        return $query->where('context', $context);
    }
}