<?php

namespace App\Requests\Concerns;

use App\Framework\Exceptions\ValidationException;

trait HandlesSubscriptionVoucherFields
{
    protected function normalizeSubscriptionVoucherFields(): void
    {
        $data = $this->data;

        $data['applies_to_subscriptions'] = $this->toBoolean($data['applies_to_subscriptions'] ?? false);
        $data['applies_to_orders'] = array_key_exists('applies_to_orders', $data)
            ? $this->toBoolean($data['applies_to_orders'])
            : true;

        $data['discount_type'] = $data['discount_type'] ?? ($data['type'] ?? null);

        if (($data['discount_type'] ?? null) === 'fixed' && !isset($data['discount_amount']) && isset($data['value'])) {
            $data['discount_amount'] = (int) round(((float) $data['value']) * 100);
        }

        if (($data['discount_type'] ?? null) === 'percentage' && !isset($data['discount_percentage']) && isset($data['value'])) {
            $data['discount_percentage'] = (int) round((float) $data['value']);
        }

        if (array_key_exists('discount_amount', $data) && $data['discount_amount'] === '') {
            $data['discount_amount'] = null;
        }

        if (array_key_exists('discount_percentage', $data) && $data['discount_percentage'] === '') {
            $data['discount_percentage'] = null;
        }

        if (array_key_exists('subscription_duration_months', $data) && $data['subscription_duration_months'] === '') {
            $data['subscription_duration_months'] = null;
        }

        if (!isset($data['subscription_duration_months']) && isset($data['duration_in_months'])) {
            $data['subscription_duration_months'] = $data['duration_in_months'];
        }

        if (!isset($data['subscription_discount_duration']) && $data['applies_to_subscriptions']) {
            $data['subscription_discount_duration'] = !empty($data['subscription_duration_months'])
                ? 'repeating'
                : 'once';
        }

        if (!$data['applies_to_subscriptions']) {
            $data['subscription_discount_duration'] = null;
            $data['subscription_duration_months'] = null;
        }

        $this->data = $data;
    }

    protected function validateSubscriptionVoucherFields(self $request): void
    {
        $appliesToSubscriptions = $this->toBoolean($request->input('applies_to_subscriptions', false));

        if (!$appliesToSubscriptions) {
            return;
        }

        $duration = $request->input('subscription_discount_duration');
        $durationInMonths = $request->input('subscription_duration_months');
        $discountType = $request->input('discount_type', $request->input('type'));
        $discountAmount = $request->input('discount_amount');
        $discountPercentage = $request->input('discount_percentage');

        if ($duration === null || $duration === '') {
            throw new ValidationException('Subscription discount duration is required for subscription vouchers');
        }

        if (!in_array($duration, ['once', 'repeating', 'forever'], true)) {
            throw new ValidationException('Subscription discount duration must be once, repeating, or forever');
        }

        if ($duration === 'repeating') {
            if ($durationInMonths === null || $durationInMonths === '' || (int) $durationInMonths < 1) {
                throw new ValidationException('Repeating subscription vouchers require duration in months of at least 1');
            }
        } elseif ($durationInMonths !== null && $durationInMonths !== '') {
            throw new ValidationException('Duration in months can only be set for repeating subscription vouchers');
        }

        if (!empty($request->input('trial_days'))) {
            throw new ValidationException('Paid introductory discounts cannot be combined with trial days');
        }

        if ($discountType === 'fixed' && ($discountAmount === null || (int) $discountAmount < 1)) {
            throw new ValidationException('Fixed subscription discounts require a discount amount');
        }

        if ($discountType === 'percentage' && ($discountPercentage === null || (int) $discountPercentage < 1 || (int) $discountPercentage > 100)) {
            throw new ValidationException('Percentage subscription discounts require a percentage between 1 and 100');
        }
    }

    private function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
