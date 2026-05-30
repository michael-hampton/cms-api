<?php

namespace App\Requests\Members;

use App\Framework\Http\FormRequest;

class AssignPlansToSegmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'plan_ids'   => ['required', 'array', 'min:1'],
            'plan_ids.*' => ['required', 'integer', 'min:1'],
            'priority'   => ['sometimes', 'integer', 'min:1'],
            'is_active'  => ['sometimes', 'boolean'],
            'starts_at'  => ['sometimes', 'nullable', 'date'],
            'ends_at'    => ['sometimes', 'nullable', 'date', 'after:starts_at'],
        ];
    }
}