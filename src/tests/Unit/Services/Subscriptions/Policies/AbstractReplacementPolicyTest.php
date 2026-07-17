<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\Policies;

use App\DTO\Subscriptions\CancellationPolicyContext;
use App\DTO\Subscriptions\PausePolicyContext;
use App\DTO\Subscriptions\PolicyContext;
use App\DTO\Subscriptions\PolicyEvaluationResult;
use App\DTO\Subscriptions\PolicyValidationResult;
use App\DTO\Subscriptions\ReplacementUsageStatistics;
use App\Enums\Subscriptions\ReplacementLimitScope;
use App\Enums\Subscriptions\ReplacementResolution;
use App\Models\IssueDelivery;
use App\Models\ReplacementPolicy;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Subscriptions\Policies\AbstractReplacementPolicy;
use Mockery;
use PHPUnit\Framework\TestCase;

class AbstractReplacementPolicyTest extends TestCase
{
    private function makePolicyModel(bool $active = true): ReplacementPolicy
    {
        $model = Mockery::mock(ReplacementPolicy::class)->makePartial();
        $model->id = 1;
        $model->name = 'Test Policy';
        $model->active = $active;

        return $model;
    }

    private function makeContext(): PolicyContext
    {
        return new PolicyContext(
            Mockery::mock(Subscription::class)->makePartial(),
            Mockery::mock(SubscriptionPlan::class)->makePartial(),
            Mockery::mock(IssueDelivery::class)->makePartial(),
            ReplacementResolution::REPLACE,
            new ReplacementUsageStatistics(0, 0),
            7,
            10,
            0
        );
    }

    public function test_id_and_name_delegate_to_the_underlying_model(): void
    {
        $policy = new class ($this->makePolicyModel()) extends AbstractReplacementPolicy {
            public function evaluate(PolicyContext $context): PolicyEvaluationResult
            {
                return PolicyEvaluationResult::allowed();
            }

            public function replacementLimitScope(): ReplacementLimitScope
            {
                return ReplacementLimitScope::PER_ISSUE;
            }

            public function extensionLimitScope(): ReplacementLimitScope
            {
                return ReplacementLimitScope::PER_ISSUE;
            }

            public function evaluateCancellation(CancellationPolicyContext $context): PolicyEvaluationResult
            {
                // Provide a minimal dummy return value suitable for your test context
                return PolicyEvaluationResult::allowed();
            }

            public function evaluatePause(PausePolicyContext $context): PolicyEvaluationResult
            {
                // Provide a minimal dummy return value suitable for your test context
                return PolicyEvaluationResult::allowed();
            }

            public static function overridableSettings(): array
            {
                // TODO: Implement overridableSettings() method.
            }
        };

        $this->assertSame(1, $policy->id());
        $this->assertSame('Test Policy', $policy->name());
    }

    public function test_validate_rejects_an_inactive_policy(): void
    {
        $policy = new class ($this->makePolicyModel(active: false)) extends AbstractReplacementPolicy {
            public function evaluate(PolicyContext $context): PolicyEvaluationResult
            {
                return PolicyEvaluationResult::allowed();
            }

            public function replacementLimitScope(): ReplacementLimitScope
            {
                return ReplacementLimitScope::PER_ISSUE;
            }

            public function extensionLimitScope(): ReplacementLimitScope
            {
                return ReplacementLimitScope::PER_ISSUE;
            }

            public function evaluateCancellation(CancellationPolicyContext $context): PolicyEvaluationResult
            {
                // Provide a minimal dummy return value suitable for your test context
                return PolicyEvaluationResult::allowed();
            }

            public function evaluatePause(PausePolicyContext $context): PolicyEvaluationResult
            {
                // Provide a minimal dummy return value suitable for your test context
                return PolicyEvaluationResult::allowed();
            }

            public static function overridableSettings(): array
            {
                // TODO: Implement overridableSettings() method.
            }
        };

        $result = $policy->validate($this->makeContext());

        $this->assertFalse($result->valid);
        $this->assertSame('This replacement policy is not active.', $result->reason);
    }

    public function test_validate_delegates_to_validate_policy_hook_when_active(): void
    {
        $policy = new class ($this->makePolicyModel()) extends AbstractReplacementPolicy {
            public function evaluate(PolicyContext $context): PolicyEvaluationResult
            {
                return PolicyEvaluationResult::allowed();
            }

            protected function validatePolicy(PolicyContext $context): PolicyValidationResult
            {
                return PolicyValidationResult::invalid('Custom rejection.');
            }

            public function replacementLimitScope(): ReplacementLimitScope
            {
                return ReplacementLimitScope::PER_ISSUE;
            }

            public function extensionLimitScope(): ReplacementLimitScope
            {
                return ReplacementLimitScope::PER_ISSUE;
            }

            public function evaluateCancellation(CancellationPolicyContext $context): PolicyEvaluationResult
            {
                // Provide a minimal dummy return value suitable for your test context
                return PolicyEvaluationResult::allowed();
            }

            public function evaluatePause(PausePolicyContext $context): PolicyEvaluationResult
            {
                // Provide a minimal dummy return value suitable for your test context
                return PolicyEvaluationResult::allowed();
            }

            public static function overridableSettings(): array
            {
                // TODO: Implement overridableSettings() method.
            }
        };

        $result = $policy->validate($this->makeContext());

        $this->assertFalse($result->valid);
        $this->assertSame('Custom rejection.', $result->reason);
    }

    public function test_limit_reached_treats_null_as_unlimited(): void
    {
        $policy = new class ($this->makePolicyModel()) extends AbstractReplacementPolicy {
            public function evaluate(PolicyContext $context): PolicyEvaluationResult
            {
                return PolicyEvaluationResult::allowed();
            }

            public function replacementLimitScope(): ReplacementLimitScope
            {
                return ReplacementLimitScope::PER_ISSUE;
            }

            public function extensionLimitScope(): ReplacementLimitScope
            {
                return ReplacementLimitScope::PER_ISSUE;
            }

            public function callLimitReached(?int $max, int $used): bool
            {
                return $this->limitReached($max, $used);
            }

            public function evaluateCancellation(CancellationPolicyContext $context): PolicyEvaluationResult
            {
                // Provide a minimal dummy return value suitable for your test context
                return PolicyEvaluationResult::allowed();
            }

            public function evaluatePause(PausePolicyContext $context): PolicyEvaluationResult
            {
                // Provide a minimal dummy return value suitable for your test context
                return PolicyEvaluationResult::allowed();
            }

            public static function overridableSettings(): array
            {
                // TODO: Implement overridableSettings() method.
            }
        };

        $this->assertFalse($policy->callLimitReached(null, 999));
        $this->assertTrue($policy->callLimitReached(2, 2));
        $this->assertFalse($policy->callLimitReached(2, 1));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
