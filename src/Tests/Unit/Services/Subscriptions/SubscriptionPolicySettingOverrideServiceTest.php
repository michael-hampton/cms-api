<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\PolicySettingKey;
use App\Events\Subscriptions\SubscriptionPolicySettingOverrideCleared;
use App\Events\Subscriptions\SubscriptionPolicySettingOverridden;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Models\SubscriptionPolicySettingOverride;
use App\Repositories\Subscriptions\SubscriptionPolicySettingOverrideRepository;
use App\Services\Subscriptions\Policies\StandardConsumerPolicy;
use App\Services\Subscriptions\SubscriptionPolicySettingOverrideService;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class SubscriptionPolicySettingOverrideServiceTest extends TestCase
{
    private SubscriptionPolicySettingOverrideRepository|MockInterface $repository;
    private Database|MockInterface $database;
    private EventDispatcher|MockInterface $eventDispatcher;
    private SubscriptionPolicySettingOverrideService $service;

    // =========================================================================
    // Happy path — setOverride
    // =========================================================================

    public function test_it_sets_an_override_inside_a_transaction_and_emits_an_event(): void
    {
        $override = $this->makeOverride();

        $this->database->expects('transaction')->once()->andReturnUsing(fn (callable $cb) => $cb());

        $this->repository->expects('deactivateActive')
            ->once()
            ->with(5, StandardConsumerPolicy::class, 'pause_allowed');

        $this->repository->expects('create')
            ->once()
            ->with([
                'site_id' => 5,
                'policy_class' => StandardConsumerPolicy::class,
                'setting_key' => 'pause_allowed',
                'value' => false,
                'reason' => 'Retention exception for VIP account',
                'created_by_user_id' => 99,
                'active' => true,
            ])
            ->andReturn($override);

        $this->eventDispatcher->expects('dispatch')
            ->once()
            ->with(Mockery::on(fn ($event) => $event instanceof SubscriptionPolicySettingOverridden
                && $event->override === $override
                && $event->adminUserId === 99));

        $result = $this->service->setOverride(
            siteId: 5,
            policyClass: StandardConsumerPolicy::class,
            settingKey: PolicySettingKey::PAUSE_ALLOWED,
            value: false,
            reason: 'Retention exception for VIP account',
            adminUserId: 99,
        );

        $this->assertSame($override, $result);
    }

    public function test_it_accepts_a_null_value_for_the_nullable_pause_limit_setting(): void
    {
        $override = $this->makeOverride();

        $this->database->allows('transaction')->andReturnUsing(fn (callable $cb) => $cb());
        $this->repository->allows('deactivateActive');
        $this->repository->expects('create')
            ->once()
            ->with(Mockery::on(fn (array $attrs) => $attrs['value'] === null))
            ->andReturn($override);
        $this->eventDispatcher->allows('dispatch');

        $this->service->setOverride(
            siteId: 5,
            policyClass: StandardConsumerPolicy::class,
            settingKey: PolicySettingKey::PAUSE_LIMIT_PER_TERM,
            value: null,
            reason: 'Unlimited pauses for this account',
            adminUserId: 99,
        );

        $this->addToAssertionCount(1);
    }

    // =========================================================================
    // Validation
    // =========================================================================

    public function test_it_rejects_a_setting_the_policy_class_does_not_declare_as_overridable(): void
    {
        $this->database->expects('transaction')->never();
        $this->eventDispatcher->expects('dispatch')->never();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not an overridable setting/');

        // GoodwillPolicy declares no overridable settings (see its
        // overridableSettings() docblock).
        $this->service->setOverride(
            siteId: 5,
            policyClass: \App\Services\Subscriptions\Policies\GoodwillPolicy::class,
            settingKey: PolicySettingKey::PAUSE_ALLOWED,
            value: false,
            reason: 'Attempted override',
            adminUserId: 99,
        );
    }

    public function test_it_rejects_a_policy_class_that_does_not_implement_the_interface(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not a valid subscription policy class/');

        $this->service->setOverride(
            siteId: 5,
            policyClass: \stdClass::class,
            settingKey: PolicySettingKey::PAUSE_ALLOWED,
            value: false,
            reason: 'Attempted override',
            adminUserId: 99,
        );
    }

    public function test_it_rejects_a_non_boolean_value_for_a_boolean_setting(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be a bool/');

        $this->service->setOverride(
            siteId: 5,
            policyClass: StandardConsumerPolicy::class,
            settingKey: PolicySettingKey::PAUSE_ALLOWED,
            value: 'yes',
            reason: 'Attempted override',
            adminUserId: 99,
        );
    }

    public function test_it_rejects_a_non_integer_non_null_value_for_the_pause_limit_setting(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be a nullable_int/');

        $this->service->setOverride(
            siteId: 5,
            policyClass: StandardConsumerPolicy::class,
            settingKey: PolicySettingKey::PAUSE_LIMIT_PER_TERM,
            value: 'three',
            reason: 'Attempted override',
            adminUserId: 99,
        );
    }

    public function test_it_rejects_an_empty_reason(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/reason is required/');

        $this->service->setOverride(
            siteId: 5,
            policyClass: StandardConsumerPolicy::class,
            settingKey: PolicySettingKey::PAUSE_ALLOWED,
            value: false,
            reason: '   ',
            adminUserId: 99,
        );
    }

    public function test_validation_failures_never_touch_the_database_or_dispatch_an_event(): void
    {
        $this->database->expects('transaction')->never();
        $this->repository->expects('deactivateActive')->never();
        $this->repository->expects('create')->never();
        $this->eventDispatcher->expects('dispatch')->never();

        try {
            $this->service->setOverride(
                siteId: 5,
                policyClass: StandardConsumerPolicy::class,
                settingKey: PolicySettingKey::PAUSE_ALLOWED,
                value: false,
                reason: '',
                adminUserId: 99,
            );
        } catch (InvalidArgumentException $exception) {
            // expected — assertions above confirm no side effects occurred
        }

        $this->addToAssertionCount(1);
    }

    // =========================================================================
    // clearOverride
    // =========================================================================

    public function test_it_clears_an_override_inside_a_transaction_and_emits_an_event(): void
    {
        $this->database->expects('transaction')->once()->andReturnUsing(fn (callable $cb) => $cb());

        $this->repository->expects('deactivateActive')
            ->once()
            ->with(5, StandardConsumerPolicy::class, 'pause_allowed');

        $this->eventDispatcher->expects('dispatch')
            ->once()
            ->with(Mockery::on(fn ($event) => $event instanceof SubscriptionPolicySettingOverrideCleared
                && $event->siteId === 5
                && $event->policyClass === StandardConsumerPolicy::class
                && $event->settingKey === 'pause_allowed'
                && $event->adminUserId === 99));

        $this->service->clearOverride(
            siteId: 5,
            policyClass: StandardConsumerPolicy::class,
            settingKey: PolicySettingKey::PAUSE_ALLOWED,
            reason: 'Reverting to plan default',
            adminUserId: 99,
        );

        $this->addToAssertionCount(1);
    }

    public function test_clear_also_rejects_an_empty_reason(): void
    {
        $this->database->expects('transaction')->never();

        $this->expectException(InvalidArgumentException::class);

        $this->service->clearOverride(
            siteId: 5,
            policyClass: StandardConsumerPolicy::class,
            settingKey: PolicySettingKey::PAUSE_ALLOWED,
            reason: '',
            adminUserId: 99,
        );
    }

    // =========================================================================
    // Failure rollback
    // =========================================================================

    public function test_a_repository_failure_inside_the_transaction_propagates_and_never_dispatches_an_event(): void
    {
        $this->database->expects('transaction')->once()->andReturnUsing(fn (callable $cb) => $cb());
        $this->repository->expects('deactivateActive')->once();
        $this->repository->expects('create')->once()->andThrow(new \RuntimeException('DB write failed'));
        $this->eventDispatcher->expects('dispatch')->never();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB write failed');

        $this->service->setOverride(
            siteId: 5,
            policyClass: StandardConsumerPolicy::class,
            settingKey: PolicySettingKey::PAUSE_ALLOWED,
            value: false,
            reason: 'Retention exception',
            adminUserId: 99,
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeOverride(): SubscriptionPolicySettingOverride
    {
        return Mockery::mock(SubscriptionPolicySettingOverride::class)->makePartial();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(SubscriptionPolicySettingOverrideRepository::class);
        $this->database = Mockery::mock(Database::class);
        $this->eventDispatcher = Mockery::mock(EventDispatcher::class);

        $this->service = new SubscriptionPolicySettingOverrideService(
            $this->repository,
            $this->database,
            $this->eventDispatcher,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}