<?php

namespace App\Requests\Offers;

use App\Framework\Http\FormRequest;

class UpdateProductOfferRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'merchant_id' => 'nullable|integer|exists:merchants,id',
            'sale_price' => 'required|numeric|min_number:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'boolean',
            'status' => 'in:pending,published,rejected',
            'voucher_id' => 'nullable|integer|exists:vouchers,id',
            'rejection_reason' => 'nullable|string|max:500',
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