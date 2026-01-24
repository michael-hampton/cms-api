<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class UpdateProductOfferRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'merchant_id' => 'nullable|integer|exists:merchants,id',
            'sale_price' => 'numeric|min:0',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'sale_price.numeric' => 'Sale price must be a number',
            'sale_price.min' => 'Sale price cannot be negative',
            'start_date.date' => 'Start date must be a valid date',
            'end_date.date' => 'End date must be a valid date',
            'end_date.after' => 'End date must be after start date',
        ];
    }
}