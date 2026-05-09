<?php

namespace App\Requests\Members;

use App\Framework\Http\FormRequest;

class UpdateConsentTypeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['string', 'max:255'],
            'name' => ['string', 'max:255'],
            'description' => ['string', 'max:1000'],
            'category' => ['string', 'max:255'],
            'required' => ['boolean'],
            'retention_days' => ['integer', 'min:0'],
            'data_purposes' => ['array'],
            'is_active' => ['boolean'],
        ];
    }
}
