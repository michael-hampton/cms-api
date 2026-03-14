<?php

namespace App\Models;

use App\DTO\Briefs\BriefPresetSubtask;
use App\Models\Concerns\TracksCreator;

class BriefTemplate extends Model
{
    use TracksCreator;

    protected $table = 'brief_templates';

    protected $fillable = [
        'site_id',
        'name',
        'description',
        'type',
        'structure',
        'default_fields',
        'is_system',
        'created_by',
        // Preset columns
        'default_owner_ids',
        'default_category_tag_id',
        'default_subtasks',
    ];

    protected $casts = [
        'default_fields' => 'array',
        'is_system' => 'boolean',
        'default_owner_ids' => 'array',
        'default_subtasks' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Returns default_subtasks as a typed array of BriefPresetSubtask value objects.
     *
     * @return BriefPresetSubtask[]
     */
    public function getDefaultSubtasksTyped(): array
    {
        return array_map(
            fn(array $subtask) => BriefPresetSubtask::fromArray($subtask),
            $this->default_subtasks ?? []
        );
    }
}