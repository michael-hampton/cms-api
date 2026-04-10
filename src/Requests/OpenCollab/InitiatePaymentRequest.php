<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;

class InitiatePaymentRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }
}