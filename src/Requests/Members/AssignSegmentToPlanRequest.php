<?php

namespace App\Requests\Members;

use App\Framework\Http\FormRequest;

class AssignSegmentToPlanRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'segment_id' => ['required', 'integer', 'min:1'],
            'priority'   => ['sometimes', 'integer', 'min:1'],
            'is_active'  => ['sometimes', 'boolean'],
            'starts_at'  => ['sometimes', 'nullable', 'date'],
            'ends_at'    => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}