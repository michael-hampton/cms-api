<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\Policies;

use App\DTO\Subscriptions\CancellationPolicyContext;
use App\DTO\Subscriptions\PausePolicyContext;
use App\DTO\Subscriptions\PolicyContext;
use App\DTO\Subscriptions\ReplacementUsageStatistics;
use App\DTO\Subscriptions\SubscriptionPolicySettingOverrides;
use App\Enums\Subscriptions\PolicySettingKey;
use App\Enums\Subscriptions\ReplacementResolution;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Models\IssueDelivery;
use App\Models\ReplacementPolicy;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Subscriptions\Policies\DigitalOnlyPolicy;
use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;

class DigitalOnlyPolicyTest extends TestCase
{
    private function makeContext(ReplacementResolution $resolution, int $extensionsUsed = 0): PolicyContext
    {
        return new PolicyContext(
            Mockery::mock(Subscription::class)->makePartial(),
            Mockery::mock(SubscriptionPlan::class)->makePartial(),
            Mockery::mock(IssueDelivery::class)->makePartial(),
            $resolution,
            new ReplacementUsageStatistics(0, $extensionsUsed),
            7,
            10,
            0
        );
    }

    private function makeCancellationContext(
        SubscriptionPolicySettingOverrides $settingOverrides = new SubscriptionPolicySettingOverrides()
    ): CancellationPolicyContext {
        return new CancellationPolicyContext(
            subscription: Mockery::mock(Subscription::class)->makePartial(),
            planId: 5,
            reason: null,
            cancellationNotes: null,
            requestedCancellationDate: new DateTimeImmutable(),
            currentStatus: SubscriptionStatus::ACTIVE,
            subscriptionAgeDays: 90,
            remainingTermDays: 30,
            settingOverrides: $settingOverrides,
        );
    }

    private function makePauseContext(
        int $pausesUsedThisTerm,
        SubscriptionPolicySettingOverrides $settingOverrides = new SubscriptionPolicySettingOverrides()
    ): PausePolicyContext {
        return new PausePolicyContext(
            subscription: Mockery::mock(Subscription::class)->makePartial(),
            planId: 5,
            requestedPauseDate: new DateTimeImmutable(),
            requestedResumeDate: new DateTimeImmutable('+14 days'),
            currentStatus: SubscriptionStatus::ACTIVE,
            subscriptionAgeDays: 90,
            remainingTermDays: 30,
            pausesUsedThisTerm: $pausesUsedThisTerm,
            settingOverrides: $settingOverrides,
        );
    }

    private function makePolicy(): DigitalOnlyPolicy
    {
        $model = Mockery::mock(ReplacementPolicy::class)->makePartial();
        $model->id = 5;
        $model->name = 'Digital Only';
        $model->active = true;

        return new DigitalOnlyPolicy($model);
    }

    public function test_it_denies_physical_replacement_always(): void
    {
        $result = $this->makePolicy()->evaluate($this->makeContext(ReplacementResolution::REPLACE));

        $this->assertFalse($result->isAllowed());
        $this->assertSame('This plan does not support physical replacements.', $result->blockedReason);
    }

    public function test_it_allows_extension_within_the_limit(): void
    {
        $result = $this->makePolicy()->evaluate($this->makeContext(ReplacementResolution::EXTEND, 1));

        $this->assertTrue($result->isAllowed());
    }

    public function test_it_requires_business_override_once_the_extension_limit_is_reached(): void
    {
        $result = $this->makePolicy()->evaluate($this->makeContext(ReplacementResolution::EXTEND, 3));

        $this->assertFalse($result->isAllowed());
        $this->assertSame('The extension limit for this plan has been reached.', $result->blockedReason);
    }

    public function test_it_always_allows_cancellation(): void
    {
        $result = $this->makePolicy()->evaluateCancellation($this->makeCancellationContext());

        $this->assertTrue($result->isAllowed());
    }

    public function test_it_denies_a_second_pause_within_the_same_term(): void
    {
        $result = $this->makePolicy()->evaluatePause($this->makePauseContext(1));

        $this->assertFalse($result->isAllowed());
    }

    // =========================================================================
    // Admin per-site setting overrides
    // =========================================================================

    public function test_a_cancellation_allowed_override_of_false_denies_cancellation(): void
    {
        $overrides = new SubscriptionPolicySettingOverrides([
            PolicySettingKey::CANCELLATION_ALLOWED->value => false,
        ]);

        $result = $this->makePolicy()->evaluateCancellation($this->makeCancellationContext($overrides));

        $this->assertFalse($result->isAllowed());
        $this->assertSame('Cancellation is not permitted on this plan.', $result->blockedReason);
    }

    public function test_a_pause_limit_override_allows_a_second_pause_within_the_same_term(): void
    {
        $overrides = new SubscriptionPolicySettingOverrides([
            PolicySettingKey::PAUSE_LIMIT_PER_TERM->value => 2,
        ]);

        $result = $this->makePolicy()->evaluatePause($this->makePauseContext(1, $overrides));

        $this->assertTrue($result->isAllowed());
    }

    public function test_a_pause_allowed_override_of_false_denies_pausing_outright(): void
    {
        $overrides = new SubscriptionPolicySettingOverrides([
            PolicySettingKey::PAUSE_ALLOWED->value => false,
        ]);

        $result = $this->makePolicy()->evaluatePause($this->makePauseContext(0, $overrides));

        $this->assertFalse($result->isAllowed());
        $this->assertSame('Pausing is not available on this plan.', $result->blockedReason);
    }

    public function test_overridable_settings_declares_the_class_defaults(): void
    {
        $this->assertSame(
            [
                'pause_allowed' => true,
                'pause_limit_per_term' => 1,
                'pause_requires_manager_approval' => false,
                'cancellation_allowed' => true,
                'cancellation_requires_manager_approval' => false,
            ],
            DigitalOnlyPolicy::overridableSettings()
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}