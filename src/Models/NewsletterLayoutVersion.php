<?php

namespace App\Models;

use App\Enums\Newsletters\LayoutVersionState;

/**
 * @property int $id
 * @property int $layout_id
 * @property int $version_number
 * @property array $layout_definition_json
 * @property string|null $migration_script_reference
 * @property string $state
 * @property string $created_at
 */
class NewsletterLayoutVersion extends Model
{
    protected $table = 'newsletter_layout_versions';

    public $timestamps = false;

    protected $fillable = [
        'layout_id',
        'version_number',
        'layout_definition_json',
        'migration_script_reference',
        'state',
        'created_at',
    ];

    protected $casts = [
        'layout_definition_json' => 'array',
        'version_number' => 'integer',
    ];

    public function state(): LayoutVersionState
    {
        return LayoutVersionState::from($this->state);
    }

    public function isPublished(): bool
    {
        return $this->state === LayoutVersionState::Published->value;
    }

    public function layout(): ?Model
    {
        return NewsletterLayout::find($this->layout_id);
    }

    public function slots(): array
    {
        return $this->layout_definition_json['slots'] ?? [];
    }
}