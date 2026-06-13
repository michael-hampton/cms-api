<?php

namespace App\Requests\OpenCollab\Briefs;

use App\Framework\Http\FormRequest;

class RequestBriefClarificationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:3000'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Message is required.',
            'message.max' => 'Message must be 3000 characters or fewer.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('message')) {
            $this->put('message', trim((string) $this->input('message')));
        }
    }
}
