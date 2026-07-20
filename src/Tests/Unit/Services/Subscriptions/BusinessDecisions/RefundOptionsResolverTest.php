<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\BusinessDecisions;

use App\Enums\Subscriptions\BusinessDecisions\BusinessDecisionCategoryEnum;
use App\Models\BusinessDecision;
use App\Models\RefundReasonPolicy;
use App\Repositories\Subscriptions\BusinessDecisions\RefundReasonPolicyRepository;
use App\Repositories\Subscriptions\BusinessDecisions\RefundReasonRepository;
use App\Services\Subscriptions\BusinessDecisions\BusinessDecisionChainResolver;
use App\Services\Subscriptions\BusinessDecisions\RefundOptionsResolver;
use Mockery;
use PHPUnit\Framework\TestCase;

class RefundOptionsResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_resolves_each_refund_field_through_the_policy_chain(): void
    {
        $chainResolver = Mockery::mock(BusinessDecisionChainResolver::class);
        $reasons = Mockery::mock(RefundReasonRepository::class);
        $policies = Mockery::mock(RefundReasonPolicyRepository::class);
        $resolver = new RefundOptionsResolver($chainResolver, $reasons, $policies);
        $product = $this->decision(1);
        $brand = $this->decision(2);
        $default = $this->decision(3);
        $chain = ['product' => $product, 'brand' => $brand, 'default' => $default];

        $chainResolver->shouldReceive('resolveChain')->once()->with(BusinessDecisionCategoryEnum::REFUNDS, 5, 6)->andReturn($chain);
        $chainResolver->shouldReceive('resolveDecision')->once()->andReturn(['decision' => $product, 'source' => 'product']);
        $policies->shouldReceive('findForDecisionAndReason')->with(1, 42)->andReturn($this->policy([
            'allow_full' => false, 'allow_pro_rated' => null, 'allow_manual' => null,
            'allow_cancel_at_period_end' => null, 'allow_cancel_immediately_no_refund' => null,
            'refund_max_percent' => 75, 'manager_approval_threshold_percent' => null,
            'default_notify_customer' => null, 'requires_internal_notes' => null,
        ]));
        $policies->shouldReceive('findForDecisionAndReason')->with(2, 42)->andReturn($this->policy([
            'allow_full' => null, 'allow_pro_rated' => true, 'allow_manual' => null,
            'allow_cancel_at_period_end' => null, 'allow_cancel_immediately_no_refund' => null,
            'refund_max_percent' => null, 'manager_approval_threshold_percent' => 40,
            'default_notify_customer' => false, 'requires_internal_notes' => null,
        ]));
        $policies->shouldReceive('findForDecisionAndReason')->with(3, 42)->andReturn(null);

        $options = $resolver->resolveOptionsForReasonId(5, 6, 42);

        self::assertFalse($options->allowFull);
        self::assertTrue($options->allowProRated);
        self::assertSame(75, $options->refundMaxPercent);
        self::assertSame(40, $options->managerApprovalThresholdPercent);
        self::assertFalse($options->defaultNotifyCustomer);
        self::assertTrue($options->allowManual);
    }

    private function decision(int $id): BusinessDecision
    {
        $decision = Mockery::mock(BusinessDecision::class)->makePartial();
        $decision->id = $id;
        $decision->category = BusinessDecisionCategoryEnum::REFUNDS;
        return $decision;
    }

    private function policy(array $fields): RefundReasonPolicy
    {
        $policy = Mockery::mock(RefundReasonPolicy::class)->makePartial();
        foreach ($fields as $field => $value) {
            $policy->{$field} = $value;
        }
        return $policy;
    }
}
