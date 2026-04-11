<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;

class RecordViolationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:plagiarism,spam,misinformation,policy,quality,other'],
            'severity' => ['required', 'string', 'in:low,medium,high'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'action_taken' => ['string', 'in:warning,suspension,ban'],
            'page_id' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'A violation type is required.',
            'severity.required' => 'A severity level is required.',
            'reason.required' => 'A reason is required.',
            'reason.min' => 'Please provide a more detailed reason (minimum 10 characters).',
        ];
    }
}