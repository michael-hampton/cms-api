<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;

class ResolveEscalationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'resolution' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}