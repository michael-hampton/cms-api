<?php

namespace App\Requests\Subscription;

use App\Framework\Http\FormRequest;

class UpdateSubscriptionPlanRequest extends FormRequest
{
    public function prepareForValidation(): void
    {
        $booleanFields = [
            'is_active',
            'is_featured',
            'is_upgrade_option',
            'print_shipping_required',
            'pre_release_enabled',
        ];

        $cast = [];
        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $cast[$field] = filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN);
            }
        }

        if (!empty($cast)) {
            $this->merge($cast);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['string', 'max:255'],
            'description' => ['string'],
            'billing_period' => ['required', 'in:weekly,monthly,quarterly,yearly,annual'],
            'price' => ['required', 'numeric', 'min_number:0'],
            'currency' => ['required', 'string', 'max:3'],
            'duration_months' => ['integer', 'min_number:1'],
            'issue_count' => ['integer', 'min_number:1'],
            'sort_order' => ['integer'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'is_upgrade_option' => ['boolean'],
            'print_shipping_required' => ['boolean'],
            'pre_release_enabled' => ['boolean'],
        ];
    }
}