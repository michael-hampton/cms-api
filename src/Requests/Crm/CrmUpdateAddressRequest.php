<?php

namespace App\Requests\Crm;

use App\Framework\Http\FormRequest;

class CrmUpdateAddressRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:shipping,billing,both'],
            'label' => ['nullable', 'string', 'max:100'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:100'],
        ];
    }
}