<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;

class AcknowledgeGuidelinesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'agreed' => ['required', 'boolean', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'agreed.accepted' => 'You must acknowledge the brand guidelines before continuing.',
        ];
    }
}