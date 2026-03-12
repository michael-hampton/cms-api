<?php

namespace App\Requests\Merchant;

use App\Framework\Http\FormRequest;

class CreateMerchantContactRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'name' => 'required',
            'email' => 'required|email',
            'merchant_id' => 'required|integer'
        ];
    }
}