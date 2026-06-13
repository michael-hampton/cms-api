<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;

class RequestChangesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'notes' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }
}