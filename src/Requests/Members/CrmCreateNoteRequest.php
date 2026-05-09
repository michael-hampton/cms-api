<?php

namespace App\Requests\Members;

use App\Framework\Http\FormRequest;

class CrmCreateNoteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}