<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;

class StoreTermsFromDocumentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'semantic_version' => ['required', 'string', 'max:32', 'regex:/^\d+\.\d+\.\d+$/'],
            'title' => ['required', 'string', 'max:255'],
            'document' => ['required'],
            'is_material_change' => ['nullable', 'boolean'],
            'change_summary' => ['nullable', 'string'],
        ];
    }
}
