<?php

namespace App\Models;

use App\Framework\Container;
use App\Framework\View\ViewRenderer;

class Block extends Model
{
    protected $table = 'blocks';
    protected $fillable = ['page_id', 'type', 'data', 'order', 'created_at', 'updated_at'];

    // Cast JSON data automatically
    protected $casts = [
        'data' => 'json'
    ];

    public function page(): ?Model
    {
        return $this->belongsTo(Page::class, 'page_id', 'id');
    }

    public function getDataAttribute()
    {
        $rawData = $this->attributes['data'] ?? null;
        return $rawData ? json_decode($rawData, true) : null;
    }

    public function setDataAttribute($value): void
    {
        $this->attributes['data'] = is_array($value) ? json_encode($value) : $value;
    }

    public function render(): string
    {
        $renderer = Container::getInstance()->resolve(ViewRenderer::class);
        $templateName = 'blocks.' . $this->type;

        if ($renderer->exists($templateName)) {
            return $renderer->render($templateName, [
                'block' => $this,
                'data' => $this->data
            ]);
        }

        return $renderer->render('blocks.default', [
            'block' => $this,
            'data' => $this->data
        ]);
    }
}