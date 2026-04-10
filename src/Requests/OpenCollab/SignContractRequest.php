<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;

class SignContractRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'contract_id' => ['required', 'integer'],
            'agreed' => ['required', 'boolean', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'agreed.accepted' => 'You must agree to the contract before continuing.',
        ];
    }
}