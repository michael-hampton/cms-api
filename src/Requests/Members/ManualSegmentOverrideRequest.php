<?php

namespace App\Requests\Members;

use App\Framework\Http\FormRequest;

class ManualSegmentOverrideRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'segment_id' => ['required', 'integer', 'min:1'],
            'reason'     => ['required', 'string', 'min:1', 'max:1000'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ];
    }
}