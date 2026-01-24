<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class CreateProductOfferRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'merchant_id' => 'nullable|integer|exists:merchants,id',
            'sale_price' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'sale_price.required' => 'Sale price is required',
            'sale_price.numeric' => 'Sale price must be a number',
            'sale_price.min' => 'Sale price cannot be negative',
            'start_date.required' => 'Start date is required',
            'start_date.date' => 'Start date must be a valid date',
            'end_date.required' => 'End date is required',
            'end_date.date' => 'End date must be a valid date',
            'end_date.after' => 'End date must be after start date',
        ];
    }
}