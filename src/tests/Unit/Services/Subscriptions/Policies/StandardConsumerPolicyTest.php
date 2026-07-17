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
use App\Services\Subscriptions\Policies\StandardConsumerPolicy;
use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;

class StandardConsumerPolicyTest extends TestCase
{
    private function makeContext(ReplacementResolution $resolution, ReplacementUsageStatistics $usage): PolicyContext
    {
        return new PolicyContext(
            Mockery::mock(Subscription::class)->makePartial(),
            Mockery::mock(SubscriptionPlan::class)->makePartial(),
            Mockery::mock(IssueDelivery::class)->makePartial(),
            $resolution,
            $usage,
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
            planId: 2,
            reason: null,
            cancellationNotes: null,
            requestedCancellationDate: new DateTimeImmutable(),
            currentStatus: SubscriptionStatus::ACTIVE,
            subscriptionAgeDays: 30,
            remainingTermDays: 10,
            settingOverrides: $settingOverrides,
        );
    }

    private function makePauseContext(
        int $pausesUsedThisTerm,
        SubscriptionPolicySettingOverrides $settingOverrides = new SubscriptionPolicySettingOverrides()
    ): PausePolicyContext {
        return new PausePolicyContext(
            subscription: Mockery::mock(Subscription::class)->makePartial(),
            planId: 2,
            requestedPauseDate: new DateTimeImmutable(),
            requestedResumeDate: new DateTimeImmutable('+14 days'),
            currentStatus: SubscriptionStatus::ACTIVE,
            subscriptionAgeDays: 30,
            remainingTermDays: 10,
            pausesUsedThisTerm: $pausesUsedThisTerm,
            settingOverrides: $settingOverrides,
        );
    }

    private function makePolicy(): StandardConsumerPolicy
    {
        $model = Mockery::mock(ReplacementPolicy::class)->makePartial();
        $model->id = 2;
        $model->name = 'Standard Consumer';
        $model->active = true;

        return new StandardConsumerPolicy($model);
    }

    public function test_it_allows_replacement_within_the_limit(): void
    {
        $result = $this->makePolicy()->evaluate(
            $this->makeContext(ReplacementResolution::REPLACE, new ReplacementUsageStatistics(1, 0))
        );

        $this->assertTrue($result->isAllowed());
    }

    public function test_it_requires_business_override_once_the_replacement_limit_is_reached(): void
    {
        $result = $this->makePolicy()->evaluate(
            $this->makeContext(ReplacementResolution::REPLACE, new ReplacementUsageStatistics(2, 0))
        );

        $this->assertFalse($result->isAllowed());
        $this->assertSame('The replacement limit for this plan has been reached.', $result->blockedReason);
    }

    public function test_it_allows_extension_within_the_limit(): void
    {
        $result = $this->makePolicy()->evaluate(
            $this->makeContext(ReplacementResolution::EXTEND, new ReplacementUsageStatistics(0, 1))
        );

        $this->assertTrue($result->isAllowed());
    }

    public function test_it_requires_business_override_once_the_extension_limit_is_reached(): void
    {
        $result = $this->makePolicy()->evaluate(
            $this->makeContext(ReplacementResolution::EXTEND, new ReplacementUsageStatistics(0, 2))
        );

        $this->assertFalse($result->isAllowed());
        $this->assertSame('The extension limit for this plan has been reached.', $result->blockedReason);
    }

    public function test_it_always_allows_cancellation(): void
    {
        $result = $this->makePolicy()->evaluateCancellation($this->makeCancellationContext());

        $this->assertTrue($result->isAllowed());
    }

    public function test_it_allows_a_pause_when_none_used_this_term(): void
    {
        $result = $this->makePolicy()->evaluatePause($this->makePauseContext(0));

        $this->assertTrue($result->isAllowed());
    }

    public function test_it_denies_a_second_pause_within_the_same_term(): void
    {
        $result = $this->makePolicy()->evaluatePause($this->makePauseContext(1));

        $this->assertFalse($result->isAllowed());
        $this->assertSame(
            'This plan allows 1 pause(s) per subscription term, which has already been used.',
            $result->blockedReason
        );
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

    public function test_a_cancellation_manager_approval_override_requires_approval(): void
    {
        $overrides = new SubscriptionPolicySettingOverrides([
            PolicySettingKey::CANCELLATION_REQUIRES_MANAGER_APPROVAL->value => true,
        ]);

        $result = $this->makePolicy()->evaluateCancellation($this->makeCancellationContext($overrides));

        $this->assertFalse($result->isAllowed());
        $this->assertSame(
            'This plan requires manager approval before a cancellation can be processed.',
            $result->blockedReason
        );
    }

    public function test_a_pause_allowed_override_of_false_denies_a_pause_that_would_otherwise_be_allowed(): void
    {
        $overrides = new SubscriptionPolicySettingOverrides([
            PolicySettingKey::PAUSE_ALLOWED->value => false,
        ]);

        $result = $this->makePolicy()->evaluatePause($this->makePauseContext(0, $overrides));

        $this->assertFalse($result->isAllowed());
        $this->assertSame('Pausing is not available on this plan.', $result->blockedReason);
    }

    public function test_a_pause_limit_override_raises_the_number_of_pauses_permitted_per_term(): void
    {
        $overrides = new SubscriptionPolicySettingOverrides([
            PolicySettingKey::PAUSE_LIMIT_PER_TERM->value => 3,
        ]);

        // Would be denied under the class default (limit 1) with 1 already used.
        $result = $this->makePolicy()->evaluatePause($this->makePauseContext(1, $overrides));

        $this->assertTrue($result->isAllowed());
    }

    public function test_a_null_pause_limit_override_makes_pauses_unlimited(): void
    {
        $overrides = new SubscriptionPolicySettingOverrides([
            PolicySettingKey::PAUSE_LIMIT_PER_TERM->value => null,
        ]);

        $result = $this->makePolicy()->evaluatePause($this->makePauseContext(50, $overrides));

        $this->assertTrue($result->isAllowed());
    }

    public function test_a_pause_manager_approval_override_requires_approval(): void
    {
        $overrides = new SubscriptionPolicySettingOverrides([
            PolicySettingKey::PAUSE_REQUIRES_MANAGER_APPROVAL->value => true,
        ]);

        $result = $this->makePolicy()->evaluatePause($this->makePauseContext(0, $overrides));

        $this->assertFalse($result->isAllowed());
        $this->assertSame('Pausing this plan requires manager approval.', $result->blockedReason);
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
            StandardConsumerPolicy::overridableSettings()
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}