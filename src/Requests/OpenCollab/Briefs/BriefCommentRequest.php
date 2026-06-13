<?php

namespace App\Requests\OpenCollab\Briefs;

use App\Framework\Http\FormRequest;

class BriefCommentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:3000'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Comment is required.',
            'content.max' => 'Comment must be 3000 characters or fewer.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('content')) {
            $this->merge(['content' => trim((string) $this->input('content'))]);
        }
    }
}
