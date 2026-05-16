<?php

namespace App\Requests\Offers;

use App\Framework\Http\FormRequest;

class CreatePricingTierRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'duration_months' => ['required', 'integer', 'min:1'],
            'issue_count' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'original_price' => ['nullable', 'numeric', 'min:0'],
            'digital_price' => ['nullable', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'label' => ['nullable', 'string', 'max:100'],
            'period_description' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'trial_days' => [
                'nullable',
                'integer',
                'min:1',
                'max:365',
            ],
//            'intro_price' => [ //todo
//                'nullable',
//                'numeric',
//                'min:0',
//                Rule::when(
//                    fn ($input) => $input->intro_price !== null,
//                    ['lt:price']   // intro price must be less than the standard recurring price
//                ),
//            ],
//            'intro_cycles' => [ //todo
//                'nullable',
//                'integer',
//                'min:1',
//                // intro_cycles requires intro_price — enforce at the validator level
//                Rule::requiredIf(fn ($input) => $input->intro_price !== null),
//            ],
        ];
    }
}