<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;

class DismissRiskMarkerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}