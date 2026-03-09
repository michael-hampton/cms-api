<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class UpdateSubscriptionPlanRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['string', 'max:255'],
            'slug' => ['string', 'max:255'],
            'description' => ['string'],
            'billing_period' => ['in:weekly,monthly,quarterly,yearly,annual'],
            'price' => ['required', 'numeric', 'min_number:0'],
            'currency' => ['required', 'string', 'max:3'],
            'duration_months' => ['integer', 'min_number:1'],
            'issue_count' => ['integer', 'min_number:1'],
            'sort_order' => ['integer'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
        ];
    }
}