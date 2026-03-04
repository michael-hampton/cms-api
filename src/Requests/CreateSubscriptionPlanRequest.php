<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;
use App\Framework\Support\Str;

class CreateSubscriptionPlanRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['string', 'max:255'],
            'description' => ['string'],
            'billing_period' => ['required', 'in:weekly,monthly,quarterly,yearly,annual'],
            'price' => ['numeric', 'min:0'],
            'currency' => ['string', 'max:3'],
            'duration_months' => ['integer', 'min:1'],
            'issue_count' => ['integer', 'min:1'],
            'sort_order' => ['integer'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            // Image uploads handled separately via hasFile() checks
        ];
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->data['slug']) && !empty($this->data['name'])) {
            $this->data['slug'] = Str::slug($this->data['name']);
        }
    }
}