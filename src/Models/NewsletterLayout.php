<?php

namespace App\Models;

use App\Enums\Newsletters\LayoutVersionState;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property array $layout_definition_json
 * @property bool $is_system_layout
 * @property int|null $created_by
 * @property string $created_at
 * @property string $updated_at
 */
class NewsletterLayout extends Model
{
    protected $table = 'newsletter_layouts';

    protected $fillable = [
        'name',
        'slug',
        'layout_definition_json',
        'is_system_layout',
        'created_by',
    ];

    protected $casts = [
        'layout_definition_json' => 'array',
        'is_system_layout' => 'boolean',
    ];

    public function versions(): \App\Framework\Support\Collection
    {
        return NewsletterLayoutVersion::where('layout_id', $this->id)
            ->orderBy('version_number', 'desc')
            ->get();
    }

    public function latestPublishedVersion(): ?NewsletterLayoutVersion
    {
        return NewsletterLayoutVersion::where('layout_id', $this->id)
            ->where('state', LayoutVersionState::Published->value)
            ->orderBy('version_number', 'desc')
            ->first();
    }

    public function latestVersion(): ?NewsletterLayoutVersion
    {
        return NewsletterLayoutVersion::where('layout_id', $this->id)
            ->orderBy('version_number', 'desc')
            ->first();
    }

    public function isDeletable(): bool
    {
        return !$this->is_system_layout;
    }
}