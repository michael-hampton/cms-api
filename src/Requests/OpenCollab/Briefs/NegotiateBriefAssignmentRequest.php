<?php

namespace App\Requests\OpenCollab\Briefs;

use App\Framework\Http\FormRequest;

class NegotiateBriefAssignmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:3000'],
            'requested_deadline' => ['nullable', 'date', 'after:now'],
            'scope_details' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Message is required.',
            'message.max' => 'Message must be 3000 characters or fewer.',
            'requested_deadline.date' => 'Requested deadline must be a valid date.',
            'requested_deadline.after' => 'Requested deadline cannot be in the past.',
            'scope_details.max' => 'Scope details must be 5000 characters or fewer.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $requestedDeadline = trim((string) $this->input('requested_deadline', ''));
        $scopeDetails = trim((string) $this->input('scope_details', ''));

        $this->put('message', trim((string) $this->input('message', '')));
        $this->put(
            'requested_deadline',
            $requestedDeadline !== '' ? $requestedDeadline : null,
        );
        $this->put(
            'scope_details',
            $scopeDetails !== '' ? $scopeDetails : null,
        );
    }
}
