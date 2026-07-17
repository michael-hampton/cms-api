<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\PolicySettingKey;
use App\Framework\Support\Collection;
use App\Models\SubscriptionPolicySettingOverride;
use App\Repositories\Subscriptions\SubscriptionPolicySettingOverrideRepository;
use App\Services\Subscriptions\PolicySettingOverrideResolver;
use App\Services\Subscriptions\Policies\StandardConsumerPolicy;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class PolicySettingOverrideResolverTest extends TestCase
{
    private SubscriptionPolicySettingOverrideRepository|MockInterface $repository;
    private PolicySettingOverrideResolver $resolver;

    public function test_it_returns_no_overrides_when_none_are_active(): void
    {
        $this->repository->allows('activeForSitePolicy')
            ->with(5, StandardConsumerPolicy::class)
            ->andReturn(new Collection([]));

        $result = $this->resolver->resolveForSitePolicy(5, StandardConsumerPolicy::class);

        $this->assertFalse($result->has(PolicySettingKey::PAUSE_ALLOWED));
        $this->assertSame('fallback', $result->get(PolicySettingKey::PAUSE_ALLOWED, 'fallback'));
    }

    public function test_it_folds_active_override_rows_into_the_value_object(): void
    {
        $override = Mockery::mock(SubscriptionPolicySettingOverride::class)->makePartial();
        $override->setting_key = 'pause_allowed';
        $override->value = false;

        $this->repository->allows('activeForSitePolicy')
            ->with(5, StandardConsumerPolicy::class)
            ->andReturn(new Collection([$override]));

        $result = $this->resolver->resolveForSitePolicy(5, StandardConsumerPolicy::class);

        $this->assertTrue($result->has(PolicySettingKey::PAUSE_ALLOWED));
        $this->assertEmpty($result->get(PolicySettingKey::PAUSE_ALLOWED, true));
    }

    public function test_it_preserves_a_null_override_value_distinct_from_no_override(): void
    {
        $override = Mockery::mock(SubscriptionPolicySettingOverride::class)->makePartial();
        $override->setting_key = 'pause_limit_per_term';
        $override->value = null;

        $this->repository->allows('activeForSitePolicy')
            ->andReturn(new Collection([$override]));

        $result = $this->resolver->resolveForSitePolicy(5, StandardConsumerPolicy::class);

        $this->assertTrue($result->has(PolicySettingKey::PAUSE_LIMIT_PER_TERM));
        $this->assertNull($result->get(PolicySettingKey::PAUSE_LIMIT_PER_TERM, 1));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(SubscriptionPolicySettingOverrideRepository::class);
        $this->resolver = new PolicySettingOverrideResolver($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}