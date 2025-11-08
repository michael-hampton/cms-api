<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class CreateAddressRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'member_id' => 'required|integer',
            'type' => 'required|in:shipping,billing,both',
            'label' => 'nullable|string|max:100',
            'is_default' => 'nullable|boolean',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'nullable|string|max:100',
            'postcode' => 'required|string|max:20',
            'country' => 'required|string|max:100',
        ];
    }
}