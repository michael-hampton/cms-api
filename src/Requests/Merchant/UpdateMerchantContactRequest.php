<?php

namespace App\Requests\Merchant;

use App\Framework\Http\FormRequest;

class UpdateMerchantContactRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'name' => 'required',
        ];
    }
}