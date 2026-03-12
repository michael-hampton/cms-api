<?php

namespace App\Requests\Subscription;

use App\Framework\Http\FormRequest;

class UpdatePricingTierRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'duration_months' => ['required', 'integer', 'min_number:1'],
            'issue_count' => ['required', 'integer', 'min_number:1'],
            'price' => ['required', 'numeric', 'min_number:0'],
            'original_price' => ['nullable', 'numeric', 'min_number:0'],
            'digital_price' => ['nullable', 'numeric', 'min_number:0'],
            'discount_percentage' => ['nullable', 'integer', 'min_number:0', 'max_number:100'],
            'label' => ['required', 'string', 'max:100'],
            'period_description' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}