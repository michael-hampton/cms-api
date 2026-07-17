<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\Policies;

use App\DTO\Subscriptions\CancellationPolicyContext;
use App\DTO\Subscriptions\PausePolicyContext;
use App\DTO\Subscriptions\PolicyContext;
use App\DTO\Subscriptions\ReplacementUsageStatistics;
use App\DTO\Subscriptions\SubscriptionPolicySettingOverrides;
use App\Enums\Subscriptions\PolicyEvaluationOutcome;
use App\Enums\Subscriptions\PolicySettingKey;
use App\Enums\Subscriptions\ReplacementResolution;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Models\IssueDelivery;
use App\Models\ReplacementPolicy;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Subscriptions\Policies\CorporatePolicy;
use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;

class CorporatePolicyTest extends TestCase
{
    private function makeContext(ReplacementResolution $resolution): PolicyContext
    {
        return new PolicyContext(
            Mockery::mock(Subscription::class)->makePartial(),
            Mockery::mock(SubscriptionPlan::class)->makePartial(),
            Mockery::mock(IssueDelivery::class)->makePartial(),
            $resolution,
            new ReplacementUsageStatistics(0, 0),
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
            planId: 4,
            reason: null,
            cancellationNotes: null,
            requestedCancellationDate: new DateTimeImmutable(),
            currentStatus: SubscriptionStatus::ACTIVE,
            subscriptionAgeDays: 800,
            remainingTermDays: 60,
            settingOverrides: $settingOverrides,
        );
    }

    private function makePauseContext(
        SubscriptionPolicySettingOverrides $settingOverrides = new SubscriptionPolicySettingOverrides()
    ): PausePolicyContext {
        return new PausePolicyContext(
            subscription: Mockery::mock(Subscription::class)->makePartial(),
            planId: 4,
            requestedPauseDate: new DateTimeImmutable(),
            requestedResumeDate: new DateTimeImmutable('+30 days'),
            currentStatus: SubscriptionStatus::ACTIVE,
            subscriptionAgeDays: 800,
            remainingTermDays: 60,
            pausesUsedThisTerm: 0,
            settingOverrides: $settingOverrides,
        );
    }

    private function makePolicy(): CorporatePolicy
    {
        $model = Mockery::mock(ReplacementPolicy::class)->makePartial();
        $model->id = 4;
        $model->name = 'Corporate';
        $model->active = true;

        return new CorporatePolicy($model);
    }

    public function test_replacement_requires_manager_approval(): void
    {
        $result = $this->makePolicy()->evaluate($this->makeContext(ReplacementResolution::REPLACE));

        $this->assertFalse($result->isAllowed());
        $this->assertSame(PolicyEvaluationOutcome::REQUIRES_MANAGER_APPROVAL, $result->outcome);
        $this->assertSame(
            'This plan requires manager approval before a replacement can be issued.',
            $result->blockedReason
        );
    }

    public function test_extension_requires_manager_approval(): void
    {
        $result = $this->makePolicy()->evaluate($this->makeContext(ReplacementResolution::EXTEND));

        $this->assertFalse($result->isAllowed());
        $this->assertSame(PolicyEvaluationOutcome::REQUIRES_MANAGER_APPROVAL, $result->outcome);
        $this->assertSame(
            'This plan requires manager approval before an extension can be issued.',
            $result->blockedReason
        );
    }

    public function test_cancellation_requires_manager_approval(): void
    {
        $result = $this->makePolicy()->evaluateCancellation($this->makeCancellationContext());

        $this->assertFalse($result->isAllowed());
        $this->assertSame(PolicyEvaluationOutcome::REQUIRES_MANAGER_APPROVAL, $result->outcome);
        $this->assertSame(
            'This plan requires manager approval before a cancellation can be processed.',
            $result->blockedReason
        );
    }

    public function test_pause_requires_manager_approval(): void
    {
        $result = $this->makePolicy()->evaluatePause($this->makePauseContext());

        $this->assertFalse($result->isAllowed());
        $this->assertSame(PolicyEvaluationOutcome::REQUIRES_MANAGER_APPROVAL, $result->outcome);
        $this->assertSame(
            'Pausing this plan is subject to the account\'s contractual agreement and requires manager approval.',
            $result->blockedReason
        );
    }

    // =========================================================================
    // Admin per-site setting overrides
    // =========================================================================

    public function test_a_cancellation_manager_approval_override_of_false_allows_self_service_cancellation(): void
    {
        $overrides = new SubscriptionPolicySettingOverrides([
            PolicySettingKey::CANCELLATION_REQUIRES_MANAGER_APPROVAL->value => false,
        ]);

        $result = $this->makePolicy()->evaluateCancellation($this->makeCancellationContext($overrides));

        $this->assertTrue($result->isAllowed());
    }

    public function test_a_cancellation_allowed_override_of_false_denies_cancellation_outright(): void
    {
        $overrides = new SubscriptionPolicySettingOverrides([
            PolicySettingKey::CANCELLATION_ALLOWED->value => false,
        ]);

        $result = $this->makePolicy()->evaluateCancellation($this->makeCancellationContext($overrides));

        $this->assertFalse($result->isAllowed());
        $this->assertSame(PolicyEvaluationOutcome::DENIED, $result->outcome);
        $this->assertSame('Cancellation is not permitted on this plan.', $result->blockedReason);
    }

    public function test_a_pause_manager_approval_override_of_false_allows_self_service_pausing(): void
    {
        $overrides = new SubscriptionPolicySettingOverrides([
            PolicySettingKey::PAUSE_REQUIRES_MANAGER_APPROVAL->value => false,
        ]);

        $result = $this->makePolicy()->evaluatePause($this->makePauseContext($overrides));

        $this->assertTrue($result->isAllowed());
    }

    public function test_a_pause_allowed_override_of_false_denies_pausing_outright(): void
    {
        $overrides = new SubscriptionPolicySettingOverrides([
            PolicySettingKey::PAUSE_ALLOWED->value => false,
        ]);

        $result = $this->makePolicy()->evaluatePause($this->makePauseContext($overrides));

        $this->assertFalse($result->isAllowed());
        $this->assertSame(PolicyEvaluationOutcome::DENIED, $result->outcome);
        $this->assertSame('Pausing is not available on this plan.', $result->blockedReason);
    }

    public function test_a_pause_limit_override_is_enforced_once_manager_approval_is_overridden_off(): void
    {
        $overrides = new SubscriptionPolicySettingOverrides([
            PolicySettingKey::PAUSE_REQUIRES_MANAGER_APPROVAL->value => false,
            PolicySettingKey::PAUSE_LIMIT_PER_TERM->value => 1,
        ]);

        $context = new PausePolicyContext(
            subscription: Mockery::mock(Subscription::class)->makePartial(),
            planId: 4,
            requestedPauseDate: new DateTimeImmutable(),
            requestedResumeDate: new DateTimeImmutable('+30 days'),
            currentStatus: SubscriptionStatus::ACTIVE,
            subscriptionAgeDays: 800,
            remainingTermDays: 60,
            pausesUsedThisTerm: 1,
            settingOverrides: $overrides,
        );

        $result = $this->makePolicy()->evaluatePause($context);

        $this->assertFalse($result->isAllowed());
        $this->assertSame(PolicyEvaluationOutcome::DENIED, $result->outcome);
    }

    public function test_overridable_settings_declares_the_class_defaults(): void
    {
        $this->assertSame(
            [
                'pause_allowed' => true,
                'pause_limit_per_term' => null,
                'pause_requires_manager_approval' => true,
                'cancellation_allowed' => true,
                'cancellation_requires_manager_approval' => true,
            ],
            CorporatePolicy::overridableSettings()
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}