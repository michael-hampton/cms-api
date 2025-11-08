<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class UpdateAddressRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => 'nullable|in:shipping,billing,both',
            'label' => 'nullable|string|max:100',
            'is_default' => 'nullable|boolean',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
        ];
    }
}