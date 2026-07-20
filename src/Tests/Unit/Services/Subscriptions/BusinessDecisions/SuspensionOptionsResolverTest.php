<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\BusinessDecisions;

use App\Enums\Subscriptions\BusinessDecisions\BusinessDecisionCategoryEnum;
use App\Models\BusinessDecision;
use App\Models\SuspensionPolicy;
use App\Repositories\Subscriptions\BusinessDecisions\SuspensionPolicyRepository;
use App\Services\Subscriptions\BusinessDecisions\BusinessDecisionChainResolver;
use App\Services\Subscriptions\BusinessDecisions\SuspensionOptionsResolver;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class SuspensionOptionsResolverTest extends TestCase
{
    private $chainResolver;
    private $suspensionPolicyRepository;
    private SuspensionOptionsResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chainResolver = m::mock(BusinessDecisionChainResolver::class);
        $this->suspensionPolicyRepository = m::mock(SuspensionPolicyRepository::class);

        $this->resolver = new SuspensionOptionsResolver(
            $this->chainResolver,
            $this->suspensionPolicyRepository,
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    /**
     * Unlike cancellations, an entirely unconfigured chain must not
     * throw — it should reproduce today's SuspendSubscriptionAction
     * behaviour (always allowed, note required) unchanged.
     */
    public function test_defaults_to_existing_behaviour_when_nothing_configured(): void
    {
        $this->chainResolver->shouldReceive('resolveChain')
            ->with(BusinessDecisionCategoryEnum::SUSPENSIONS, 5, 6)
            ->andReturn(['product' => null, 'brand' => null, 'default' => null]);

        $this->suspensionPolicyRepository->shouldNotReceive('findForDecision');

        $options = $this->resolver->resolveForPlan(5, 6);

        $this->assertTrue($options->allowSuspend);
        $this->assertTrue($options->requiresNote);
    }

    public function test_brand_level_override_takes_precedence_over_default(): void
    {
        $brandDecision = m::mock(BusinessDecision::class)->makePartial();
        $brandDecision->id = 2;

        $defaultDecision = m::mock(BusinessDecision::class)->makePartial();
        $defaultDecision->id = 3;

        $this->chainResolver->shouldReceive('resolveChain')
            ->andReturn(['product' => null, 'brand' => $brandDecision, 'default' => $defaultDecision]);

        $brandPolicy = m::mock(SuspensionPolicy::class)->makePartial();
        $brandPolicy->allow_suspend = false;
        $brandPolicy->requires_note = null;

        $defaultPolicy = m::mock(SuspensionPolicy::class)->makePartial();
        $defaultPolicy->allow_suspend = true;
        $defaultPolicy->requires_note = false;

        $this->suspensionPolicyRepository->shouldReceive('findForDecision')->with(2)->andReturn($brandPolicy);
        $this->suspensionPolicyRepository->shouldReceive('findForDecision')->with(3)->andReturn($defaultPolicy);

        $options = $this->resolver->resolveForPlan(5, 6);

        $this->assertFalse($options->allowSuspend, 'brand explicitly disallows suspension');
        $this->assertFalse($options->requiresNote, 'brand leaves this null, so it inherits from default');
    }
}
