<?php

namespace App\Requests\Briefs;

use App\Framework\Http\FormRequest;

class UpdateBriefPresetRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'default_owner_ids' => ['nullable', 'array'],
            'default_owner_ids.*' => ['integer'],
            'default_category_tag_id' => ['nullable', 'string'],
            'default_subtasks' => ['nullable', 'array'],
            'default_subtasks.*.title' => ['required', 'string', 'max:255'],
        ];
    }
}