<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\PolicySettingKey;
use App\Events\Subscriptions\SubscriptionPolicySettingOverrideCleared;
use App\Events\Subscriptions\SubscriptionPolicySettingOverridden;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Models\SubscriptionPolicySettingOverride;
use App\Repositories\Subscriptions\SubscriptionPolicySettingOverrideRepository;
use App\Services\Subscriptions\Contracts\ReplacementPolicyInterface;
use InvalidArgumentException;

/**
 * Admin-authored per-site overrides of individual subscription policy
 * settings (pause/cancellation gates). This is the write path guarded by
 * the `subscription_policies.override` permission — SubscriptionPauseService
 * and SubscriptionCancellationService never call this directly, they only
 * read via PolicySettingOverrideResolver, keeping "who's allowed to
 * change entitlement rules" entirely out of the money-critical services.
 */
class SubscriptionPolicySettingOverrideService
{
    public function __construct(
        private readonly SubscriptionPolicySettingOverrideRepository $repository,
        private readonly Database $database,
        private readonly EventDispatcher $eventDispatcher,
    ) {
    }

    /**
     * @throws InvalidArgumentException if the policy class doesn't
     *   declare that setting as overridable, the value doesn't match the
     *   setting's expected type, or reason is empty.
     */
    public function setOverride(
        int $siteId,
        string $policyClass,
        PolicySettingKey $settingKey,
        mixed $value,
        string $reason,
        int $adminUserId,
    ): SubscriptionPolicySettingOverride {
        $this->assertSettingSupported($policyClass, $settingKey);
        $this->assertValueMatchesType($settingKey, $value);
        $this->assertReasonProvided($reason);

        return $this->database->transaction(function () use (
            $siteId,
            $policyClass,
            $settingKey,
            $value,
            $reason,
            $adminUserId,
        ) {
            $this->repository->deactivateActive($siteId, $policyClass, $settingKey->value);

            $override = $this->repository->create([
                'site_id' => $siteId,
                'policy_class' => $policyClass,
                'setting_key' => $settingKey->value,
                'value' => $value,
                'reason' => $reason,
                'created_by_user_id' => $adminUserId,
                'active' => true,
            ]);

            $this->eventDispatcher->dispatch(
                new SubscriptionPolicySettingOverridden($override, $adminUserId)
            );

            return $override;
        });
    }

    /**
     * Reverts a setting to the policy class's own default by deactivating
     * its active override, if any. A no-op (but still audited) when
     * nothing is currently overridden.
     */
    public function clearOverride(
        int $siteId,
        string $policyClass,
        PolicySettingKey $settingKey,
        string $reason,
        int $adminUserId,
    ): void {
        $this->assertSettingSupported($policyClass, $settingKey);
        $this->assertReasonProvided($reason);

        $this->database->transaction(function () use ($siteId, $policyClass, $settingKey, $reason, $adminUserId) {
            $this->repository->deactivateActive($siteId, $policyClass, $settingKey->value);

            $this->eventDispatcher->dispatch(new SubscriptionPolicySettingOverrideCleared(
                siteId: $siteId,
                policyClass: $policyClass,
                settingKey: $settingKey->value,
                reason: $reason,
                adminUserId: $adminUserId,
            ));
        });
    }

    private function assertSettingSupported(string $policyClass, PolicySettingKey $settingKey): void
    {
        if (!is_a($policyClass, ReplacementPolicyInterface::class, true)) {
            throw new InvalidArgumentException("\"{$policyClass}\" is not a valid subscription policy class.");
        }

        $supported = $policyClass::overridableSettings();

        if (!array_key_exists($settingKey->value, $supported)) {
            throw new InvalidArgumentException(
                "\"{$settingKey->value}\" is not an overridable setting for {$policyClass}."
            );
        }
    }

    private function assertValueMatchesType(PolicySettingKey $settingKey, mixed $value): void
    {
        $valid = match ($settingKey->valueType()) {
            'bool' => is_bool($value),
            'nullable_int' => $value === null || is_int($value),
            default => false,
        };

        if (!$valid) {
            throw new InvalidArgumentException(
                "Value for \"{$settingKey->value}\" must be a {$settingKey->valueType()}."
            );
        }
    }

    private function assertReasonProvided(string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reason is required to override a policy setting.');
        }
    }
}