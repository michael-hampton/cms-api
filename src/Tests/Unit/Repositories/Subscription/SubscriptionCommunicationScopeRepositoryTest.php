<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repositories\Subscription;

use App\Enums\Subscriptions\CommunicationTypeEnum;
use App\Models\SubscriptionCommunication;
use App\Repositories\Subscriptions\SubscriptionCommunicationScopeRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class SubscriptionCommunicationScopeRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private SubscriptionCommunicationScopeRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SubscriptionCommunicationScopeRepository();
    }

    public function test_defaults_to_enabled_when_no_scopes_configured(): void
    {
        $communication = SubscriptionCommunication::create($this->commAttributes());

        $this->assertTrue($this->repository->isEnabled($communication->id, $this->siteId, null));
    }

    public function test_system_default_row_applies_when_no_more_specific_scope_exists(): void
    {
        $communication = SubscriptionCommunication::create($this->commAttributes());

        $this->repository->upsertScope($communication->id, null, null, false);

        $this->assertFalse($this->repository->isEnabled($communication->id, $this->siteId, 999));
    }

    public function test_site_scope_overrides_system_default(): void
    {
        $communication = SubscriptionCommunication::create($this->commAttributes());

        $this->repository->upsertScope($communication->id, null, null, false);
        $this->repository->upsertScope($communication->id, $this->siteId, null, true);

        $this->assertTrue($this->repository->isEnabled($communication->id, $this->siteId, 999));
        // A different site still falls through to the system default.
        $this->assertFalse($this->repository->isEnabled($communication->id, 987654, 999));
    }

    public function test_site_and_plan_scope_is_most_specific(): void
    {
        $communication = SubscriptionCommunication::create($this->commAttributes());
        $plan = $this->createSubscriptionPlan();

        $this->repository->upsertScope($communication->id, $this->siteId, null, true);
        $this->repository->upsertScope($communication->id, $this->siteId, $plan->id, false);

        $this->assertFalse($this->repository->isEnabled($communication->id, $this->siteId, $plan->id));
        // Same site, different plan still gets the site-level default.
        $this->assertTrue($this->repository->isEnabled($communication->id, $this->siteId, 987654));
    }

    public function test_upsert_scope_updates_existing_row_rather_than_duplicating(): void
    {
        $communication = SubscriptionCommunication::create($this->commAttributes());

        $first = $this->repository->upsertScope($communication->id, $this->siteId, null, true);
        $second = $this->repository->upsertScope($communication->id, $this->siteId, null, false);

        $this->assertSame($first->id, $second->id);
        $this->assertFalse($this->repository->isEnabled($communication->id, $this->siteId, null));
    }

    private function commAttributes(array $overrides = []): array
    {
        return array_merge([
            'key'        => 'comm_' . uniqid(),
            'name'       => 'Test Communication ' . uniqid(),
            'type'       => CommunicationTypeEnum::PAYMENT_FAILED_NOTICE->value,
            'template'   => '',
            'channels'   => ['letter'],
            'is_active'  => true,
            'sort_order' => 0,
        ], $overrides);
    }
}
