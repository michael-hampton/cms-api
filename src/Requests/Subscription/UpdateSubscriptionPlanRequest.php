<?php

namespace App\Requests\Subscription;

use App\Enums\Subscriptions\SubscriptionDeliveryType;
use App\Enums\Subscriptions\SubscriptionEntitlementType;
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
            'name' => ['string', 'max:255'],
            'slug' => ['string', 'max:255'],
            'description' => ['string'],
            'billing_period' => ['in:weekly,monthly,quarterly,yearly,annual'],
            'entitlement_type' => ['in:' . implode(',', SubscriptionEntitlementType::values())],
            'price' => ['numeric', 'min_number:0'],
            'currency' => ['string', 'max:3'],
            'duration_months' => ['integer', 'min_number:1'],
            'issue_count' => ['integer', 'min_number:1'],
            'sort_order' => ['integer'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'is_upgrade_option' => ['boolean'],
            'delivery_type' => ['in:' . implode(',', SubscriptionDeliveryType::values())],
            'digital_download_url' => ['string'],
            'print_shipping_required' => ['boolean'],
            'pre_release_enabled' => ['boolean'],
        ];
    }
}
