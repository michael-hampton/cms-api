<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;

class UpdateTermsVersionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'source_content' => ['nullable', 'string', 'min:50'],
            'source_format' => ['nullable', 'string', 'max:20'],
            'is_material_change' => ['nullable', 'boolean'],
            'change_summary' => ['nullable', 'string'],
            'supersedes_terms_version_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
