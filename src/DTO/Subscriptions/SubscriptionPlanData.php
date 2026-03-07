<?php

namespace App\DTO\Subscriptions;

use App\Enums\Subscriptions\BillingPeriod;
use App\Framework\Support\Str;

final class SubscriptionPlanData
{
    private const ALLOWED_CURRENCIES = ['USD', 'EUR', 'GBP', 'AUD', 'CAD'];

    private function __construct(
        private readonly array $attributes
    )
    {
    }

    public static function fromArray(array $data, ?int $siteId = null): self
    {
        $prepared = [];

        if ($siteId !== null) {
            $prepared['site_id'] = $siteId;
        }

        if (isset($data['name'])) {
            $prepared['name'] = $data['name'];

            if (empty($data['slug'])) {
                $prepared['slug'] = self::generateSlug($data['name']);
            }
        }

        if (!empty($data['slug'])) {
            $prepared['slug'] = self::sanitizeSlug($data['slug']);
        }

        if (isset($data['description'])) {
            $prepared['description'] = $data['description'];
        }

        if (isset($data['price'])) {
            $price = (float)$data['price'];

            if ($price < 0) {
                throw new \InvalidArgumentException('Price cannot be negative');
            }

            $prepared['price'] = round($price, 2);
        }

        if (isset($data['currency'])) {
            $currency = strtoupper($data['currency']);

            if (!in_array($currency, self::ALLOWED_CURRENCIES, true)) {
                throw new \InvalidArgumentException("Currency {$currency} is not supported");
            }

            $prepared['currency'] = $currency;
        }

        if (isset($data['billing_period'])) {
            $billingPeriod = BillingPeriod::tryFrom($data['billing_period']);

            if (!$billingPeriod) {
                throw new \InvalidArgumentException("Invalid billing period: {$data['billing_period']}");
            }

            $prepared['billing_period'] = $billingPeriod->value;
        }

        if (isset($data['trial_days'])) {
            $trialDays = (int)$data['trial_days'];

            if ($trialDays < 0) {
                throw new \InvalidArgumentException('Trial days cannot be negative');
            }

            $prepared['trial_days'] = $trialDays;
        }

        if (isset($data['features'])) {
            $prepared['features'] = is_array($data['features'])
                ? $data['features']
                : json_decode($data['features'], true);
        }

        if (isset($data['is_active'])) {
            $prepared['is_active'] = (bool)$data['is_active'];
        }

        if (isset($data['is_featured'])) {
            $prepared['is_featured'] = (bool)$data['is_featured'];
        }

        if (isset($data['sort_order'])) {
            $prepared['sort_order'] = (int)$data['sort_order'];
        }

        if (isset($data['digital_download_url'])) {
            $prepared['digital_download_url'] = $data['digital_download_url'];
        }

        if (isset($data['print_shipping_required'])) {
            $prepared['print_shipping_required'] = (bool)$data['print_shipping_required'];
        }

        if (isset($data['includes_insider'])) {
            $prepared['includes_insider'] = (bool)$data['includes_insider'];
        }

        if (isset($data['is_upgrade_option'])) {
            $prepared['is_upgrade_option'] = (bool)$data['is_upgrade_option'];
        }

        if (isset($data['upgrade_from_plan_id'])) {
            $prepared['upgrade_from_plan_id'] = $data['upgrade_from_plan_id'] !== null
                ? (int)$data['upgrade_from_plan_id']
                : null;
        }

        if (isset($data['dispatch_days'])) {
            $prepared['dispatch_days'] = (int)$data['dispatch_days'];
        }

        if (isset($data['release_date'])) {
            $prepared['release_date'] = $data['release_date'];
        }

        if (isset($data['pre_release_enabled'])) {
            $prepared['pre_release_enabled'] = (bool)$data['pre_release_enabled'];
        }

        if (isset($data['categories'])) {
            $prepared['categories'] = is_array($data['categories'])
                ? $data['categories']
                : json_decode($data['categories'], true);
        }

        if (isset($data['tags'])) {
            $prepared['tags'] = is_array($data['tags'])
                ? $data['tags']
                : json_decode($data['tags'], true);
        }

        if (isset($data['premium_access'])) {
            $prepared['premium_access'] = is_array($data['premium_access'])
                ? $data['premium_access']
                : json_decode($data['premium_access'], true);

            if (isset($data['print_image_url'])) {
                $prepared['print_image_url'] = $data['print_image_url'];
            }

            if (isset($data['digital_image_url'])) {
                $prepared['digital_image_url'] = $data['digital_image_url'];
            }
        }

        if (isset($data['digital_download_url'])) {
            $prepared['digital_download_url'] = $data['digital_download_url'];
        }

        return new self($prepared);
    }

    private static function generateSlug(string $name): string
    {
        return Str::slug($name);
    }

    private static function sanitizeSlug(string $slug): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9-]+/', '-', $slug), '-'));
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}

