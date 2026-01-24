<?php

namespace App\Requests;

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