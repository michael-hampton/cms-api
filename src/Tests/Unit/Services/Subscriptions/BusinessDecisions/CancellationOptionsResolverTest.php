<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\BusinessDecisions;

use App\DTO\Subscriptions\SubscriptionOfferData;
use App\Enums\Subscriptions\BusinessDecisions\BusinessDecisionCategoryEnum;
use App\Models\BusinessDecision;
use App\Models\CancellationReason;
use App\Models\CancellationReasonPolicy;
use App\Repositories\Subscriptions\BusinessDecisions\CancellationReasonPolicyRepository;
use App\Repositories\Subscriptions\BusinessDecisions\CancellationReasonRepository;
use App\Services\Subscriptions\BusinessDecisions\BusinessDecisionChainResolver;
use App\Services\Subscriptions\BusinessDecisions\CancellationOptionsResolver;
use App\Services\Subscriptions\SubscriptionOfferSearchService;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class CancellationOptionsResolverTest extends TestCase
{
    private $chainResolver;
    private $reasonRepository;
    private $reasonPolicyRepository;
    private $offerSearchService;
    private CancellationOptionsResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chainResolver = m::mock(BusinessDecisionChainResolver::class);
        $this->reasonRepository = m::mock(CancellationReasonRepository::class);
        $this->reasonPolicyRepository = m::mock(CancellationReasonPolicyRepository::class);
        $this->offerSearchService = m::mock(SubscriptionOfferSearchService::class);

        $this->resolver = new CancellationOptionsResolver(
            $this->chainResolver,
            $this->reasonRepository,
            $this->reasonPolicyRepository,
            $this->offerSearchService,
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    private function makeDecision(int $id): BusinessDecision
    {
        $decision = m::mock(BusinessDecision::class)->makePartial();
        $decision->id = $id;
        $decision->category = BusinessDecisionCategoryEnum::CANCELLATIONS;
        $decision->name = "Decision {$id}";

        return $decision;
    }

    private function makePolicyRow(array $fields): CancellationReasonPolicy
    {
        $policy = m::mock(CancellationReasonPolicy::class)->makePartial();
        foreach ($fields as $key => $value) {
            $policy->{$key} = $value;
        }

        return $policy;
    }

    /**
     * The core "nullable inheritance" behaviour: a field left null at
     * the product level falls through to brand, and a field left null
     * at both falls through to the global default — independently per
     * field, not per row.
     */
    public function test_resolves_fields_independently_across_the_chain(): void
    {
        $productDecision = $this->makeDecision(1);
        $brandDecision = $this->makeDecision(2);
        $defaultDecision = $this->makeDecision(3);

        $chain = ['product' => $productDecision, 'brand' => $brandDecision, 'default' => $defaultDecision];

        $this->chainResolver->shouldReceive('resolveChain')->andReturn($chain);
        $this->chainResolver->shouldReceive('resolveDecision')
            ->andReturn(['decision' => $productDecision, 'source' => 'product']);

        // Product only overrides refund_max_percent, leaves everything else null.
        $this->reasonPolicyRepository->shouldReceive('findForDecisionAndReason')
            ->with(1, 42)
            ->andReturn($this->makePolicyRow([
                'show_save_actions' => null,
                'allow_discount' => null,
                'allow_offer_switch' => null,
                'allow_cancel' => null,
                'refund_max_percent' => 75,
                'marketing_consent' => null,
            ]));

        // Brand overrides show_save_actions and allow_discount only.
        $this->reasonPolicyRepository->shouldReceive('findForDecisionAndReason')
            ->with(2, 42)
            ->andReturn($this->makePolicyRow([
                'show_save_actions' => true,
                'allow_discount' => true,
                'allow_offer_switch' => null,
                'allow_cancel' => null,
                'refund_max_percent' => null,
                'marketing_consent' => null,
            ]));

        // Default provides the rest.
        $this->reasonPolicyRepository->shouldReceive('findForDecisionAndReason')
            ->with(3, 42)
            ->andReturn($this->makePolicyRow([
                'show_save_actions' => false,
                'allow_discount' => false,
                'allow_offer_switch' => true,
                'allow_cancel' => true,
                'refund_max_percent' => 10,
                'marketing_consent' => true,
            ]));

        $options = $this->resolver->resolveOptionsForReasonId(planId: 5, siteId: 6, cancellationReasonId: 42);

        $this->assertTrue($options->showSaveActions, 'should inherit from brand');
        $this->assertTrue($options->allowDiscount, 'should inherit from brand');
        $this->assertTrue($options->allowOfferSwitch, 'should inherit from default');
        $this->assertTrue($options->allowCancel, 'should inherit from default');
        $this->assertSame(75, $options->refundMaxPercent, 'should inherit from product');
        $this->assertTrue($options->marketingConsent, 'should inherit from default');
    }

    /**
     * If nothing in the chain has a row at all for this reason, every
     * field falls back to the resolver's own conservative defaults.
     */
    public function test_falls_back_to_conservative_defaults_when_nothing_configured(): void
    {
        $defaultDecision = $this->makeDecision(3);
        $chain = ['product' => null, 'brand' => null, 'default' => $defaultDecision];

        $this->chainResolver->shouldReceive('resolveChain')->andReturn($chain);
        $this->chainResolver->shouldReceive('resolveDecision')
            ->andReturn(['decision' => $defaultDecision, 'source' => 'default']);

        $this->reasonPolicyRepository->shouldReceive('findForDecisionAndReason')
            ->with(3, 42)
            ->andReturn(null);

        $options = $this->resolver->resolveOptionsForReasonId(planId: 5, siteId: 6, cancellationReasonId: 42);

        $this->assertFalse($options->showSaveActions);
        $this->assertFalse($options->allowDiscount);
        $this->assertFalse($options->allowOfferSwitch);
        $this->assertTrue($options->allowCancel, 'cancellation must always be possible unless explicitly blocked');
        $this->assertSame(0, $options->refundMaxPercent);
        $this->assertFalse($options->marketingConsent);
    }

    public function test_offers_are_only_attached_when_save_actions_and_offer_switch_are_both_allowed(): void
    {
        $decision = $this->makeDecision(3);
        $chain = ['product' => null, 'brand' => null, 'default' => $decision];

        $this->chainResolver->shouldReceive('resolveChain')->andReturn($chain);
        $this->chainResolver->shouldReceive('resolveDecision')
            ->andReturn(['decision' => $decision, 'source' => 'default']);

        $reason = m::mock(CancellationReason::class)->makePartial();
        $reason->id = 42;
        $reason->code = 'too_expensive';
        $reason->label = "It's too expensive";

        $this->reasonRepository->shouldReceive('listActive')
            ->andReturn(new \App\Framework\Support\Collection([$reason]));

        $this->reasonPolicyRepository->shouldReceive('findForDecisionAndReason')
            ->with(3, 42)
            ->andReturn($this->makePolicyRow([
                'show_save_actions' => true,
                'allow_discount' => true,
                'allow_offer_switch' => true,
                'allow_cancel' => true,
                'refund_max_percent' => 50,
                'marketing_consent' => false,
            ]));

        $offer = m::mock(SubscriptionOfferData::class)->makePartial();
        $this->offerSearchService->shouldReceive('search')->once()->andReturn(['items' => [$offer]]);

        $result = $this->resolver->resolveForPlan(planId: 5, siteId: 6);

        $this->assertCount(1, $result['reasons']);
        $this->assertCount(1, $result['reasons'][0]->availableOffers);
    }
}
