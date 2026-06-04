<?php

namespace App\Requests\Subscription;

use App\Enums\Subscriptions\PricingEntitlementType;
use App\Framework\Http\FormRequest;

class CreatePricingTierRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'entitlement_type'   => ['nullable', 'in:' . implode(',', PricingEntitlementType::values())],
            'duration_months'    => ['nullable', 'integer', 'min_number:1'],
            'issue_count'        => ['nullable', 'integer', 'min_number:1'],
            'price'              => ['nullable', 'numeric', 'min_number:0'],
            'sale_price'         => ['nullable', 'numeric', 'min_number:0'],
            'original_price'     => ['nullable', 'numeric', 'min_number:0'],
            'digital_price'      => ['nullable', 'numeric', 'min_number:0'],
            'digital_sale_price' => ['nullable', 'numeric', 'min_number:0'],
            'discount_percentage'=> ['nullable', 'integer', 'min_number:0', 'max_number:100'],
            'label'              => ['nullable', 'string', 'max:100'],
            'period_description' => ['nullable', 'string', 'max:255'],
            'is_default'         => ['nullable', 'boolean'],
            'is_active'          => ['nullable', 'boolean'],
            'sort_order'         => ['nullable', 'integer'],
            'currency'           => ['required', 'string'],
            'trial_days'         => ['nullable', 'integer', 'min_number:1', 'max_number:365'],
            'intro_price'        => ['nullable', 'numeric', 'min_number:0'],
            'intro_cycles'       => ['nullable', 'integer', 'min_number:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (self $request) {
                $this->validateIntroPricing($request);
            },
        ];
    }

    // Fallback if Validator::addError() doesn't exist
    private function validateIntroPricing(self $request): void
    {
        $errors = [];
        $price       = $request->input('price');
        $introPrice  = $request->input('intro_price');
        $introCycles = $request->input('intro_cycles');

        $hasIntroPrice  = $introPrice  !== null && $introPrice  !== '';
        $hasIntroCycles = $introCycles !== null && $introCycles !== '';

        if ($hasIntroPrice && !$hasIntroCycles) {
            $errors['intro_cycles'] = ['intro_cycles is required when intro_price is set.'];
        }

        if ($hasIntroCycles && !$hasIntroPrice) {
            $errors['intro_price'] = ['intro_price is required when intro_cycles is set.'];
        }

        if ($hasIntroPrice && $price !== null && (float) $introPrice >= (float) $price) {
            $errors['intro_price'] = ['Introductory price must be less than the standard price.'];
        }

        if (!empty($errors)) {
            throw new \App\Framework\Exceptions\ValidationException('Validation failed', $errors);
        }
    }
}
