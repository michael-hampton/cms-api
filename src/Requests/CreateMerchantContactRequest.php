<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class CreateMerchantContactRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'name' => 'required',
            'email' => 'required|email',
        ];
    }
}