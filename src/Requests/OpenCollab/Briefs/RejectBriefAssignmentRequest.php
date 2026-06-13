<?php

namespace App\Requests\OpenCollab\Briefs;

use App\Framework\Http\FormRequest;

class RejectBriefAssignmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Reason is required.',
            'reason.max' => 'Reason must be 1000 characters or fewer.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('reason')) {
            $this->put('reason', trim((string) $this->input('reason')));
        }
    }
}
