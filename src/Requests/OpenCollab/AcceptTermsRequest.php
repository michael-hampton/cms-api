<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;

class AcceptTermsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'terms_version_id' => ['required', 'integer', 'min:1'],
            'agreed' => ['required', 'boolean', 'accepted'],
        ];
    }
}
