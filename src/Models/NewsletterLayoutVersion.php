<?php

namespace App\Models;

use App\Enums\Newsletters\LayoutVersionState;
use App\Models\Concerns\TracksCreator;

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
    use TracksCreator;

    protected $table = 'newsletter_layout_versions';

    public $timestamps = false;

    protected $fillable = [
        'layout_id',
        'version_number',
        'layout_definition_json',
        'migration_script_reference',
        'state',
        'created_at',
        'name',
        'category',
        'description',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'layout_definition_json' => 'array',
        'version_number' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function state(): LayoutVersionState
    {
        return LayoutVersionState::from($this->state);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
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