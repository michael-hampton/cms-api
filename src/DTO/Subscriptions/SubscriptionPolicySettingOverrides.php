<?php

declare(strict_types=1);

namespace App\DTO\Subscriptions;

use App\Enums\Subscriptions\PolicySettingKey;

/**
 * The resolved, active policy-setting overrides for one site + policy
 * class, pre-populated onto PausePolicyContext/CancellationPolicyContext
 * so policies never query the override repository directly — same
 * principle as the rest of those contexts (see PausePolicyContext /
 * CancellationPolicyContext docblocks).
 *
 * get() falls back to the caller-supplied default (the policy class's own
 * const) whenever no active override exists for that key.
 */
final class SubscriptionPolicySettingOverrides
{
    /**
     * @param array<string, mixed> $values Keyed by PolicySettingKey::value
     */
    public function __construct(
        private readonly array $values = [],
    ) {
    }

    public static function none(): self
    {
        return new self([]);
    }

    public function get(PolicySettingKey $key, mixed $default): mixed
    {
        return array_key_exists($key->value, $this->values)
            ? $this->values[$key->value]
            : $default;
    }

    public function has(PolicySettingKey $key): bool
    {
        return array_key_exists($key->value, $this->values);
    }
}