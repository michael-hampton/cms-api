<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;

class RequestPayoutRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'method' => ['required', 'string', 'in:bank_transfer,paypal,other'],
        ];
    }

    public function messages(): array
    {
        return [
            'method.required' => 'A payout method is required.',
            'method.in' => 'Supported methods: bank_transfer, paypal, other.',
        ];
    }
}