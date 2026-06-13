<?php

namespace App\Requests\OpenCollab\Briefs;

use App\Framework\Http\FormRequest;

class RequestBriefDeadlineChangeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'requested_deadline' => ['required', 'date', 'after:now'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'requested_deadline.required' => 'Requested deadline is required.',
            'requested_deadline.date' => 'Requested deadline must be a valid date.',
            'requested_deadline.after' => 'Requested deadline cannot be in the past.',
            'reason.required' => 'Reason is required.',
            'reason.max' => 'Reason must be 1000 characters or fewer.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'requested_deadline' => trim((string) $this->input('requested_deadline', '')),
            'reason' => trim((string) $this->input('reason', '')),
        ]);
    }
}
