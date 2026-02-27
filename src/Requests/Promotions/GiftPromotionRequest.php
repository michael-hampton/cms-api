<?php

namespace App\Requests\Promotions;


use App\Framework\Http\FormRequest;

class GiftPromotionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required'],
            'merchant_id' => ['nullable', 'integer'],
            'gift_type' => ['required', 'string', 'max:100'],
            'gift_product_id' => ['nullable', 'integer'],
            'gift_subscription_plan_id' => ['nullable', 'integer'],
            'quantity_rule' => ['nullable', 'string', 'max:255'],
            'max_per_order' => ['nullable', 'integer', 'min:1'],
            'exclusive' => ['boolean'],
            'priority' => ['integer', 'min:0'],
            'active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'], //todo 'after_or_equal:starts_at'
            'triggers' => ['nullable', 'array'],
            'triggers.*.type' => ['required', 'string', 'max:100'],
            'triggers.*.operator' => ['required', 'string', 'max:50'],
            'triggers.*.reference_id' => ['nullable', 'integer'],
            'triggers.*.value' => ['nullable', 'numeric'],
            'triggers.*.value_set' => ['nullable', 'array'],
            'triggers.*.group_key' => ['nullable', 'string', 'max:100'],
            'triggers.*.negated' => ['boolean'],
            'excluded_issue_ids' => ['nullable', 'array'],
            'excluded_issue_ids.*' => ['integer'],
        ];
    }
}