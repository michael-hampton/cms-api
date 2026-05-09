<?php

namespace App\Requests\Members;

use App\Framework\Http\FormRequest;

class CrmUpdateNoteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}