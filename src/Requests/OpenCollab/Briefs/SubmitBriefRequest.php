<?php

namespace App\Requests\OpenCollab\Briefs;

use App\Framework\Http\FormRequest;

class SubmitBriefRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'notes.max' => 'Submission notes must be 2000 characters or fewer.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $notes = trim((string) $this->input('notes', ''));
        $this->put('notes', $notes !== '' ? $notes : null);
    }
}
