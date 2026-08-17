<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\ReplacementPolicy;
use App\Repositories\Subscriptions\ReplacementPolicyRepository;
use App\Services\Subscriptions\ReplacementPolicyService;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\TestCase;

class ReplacementPolicyServiceTest extends TestCase
{
    private $policyRepository;
    private $database;
    private $logger;
    private ReplacementPolicyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policyRepository = Mockery::mock(ReplacementPolicyRepository::class);
        $this->database = Mockery::mock(Database::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $this->service = new ReplacementPolicyService(
            $this->policyRepository,
            $this->database,
            $this->logger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function policy(array $attrs = []): ReplacementPolicy
    {
        $policy = Mockery::mock(ReplacementPolicy::class)->makePartial();
        $policy->id = $attrs['id'] ?? 1;
        $policy->is_default = $attrs['is_default'] ?? false;
        $policy->active = $attrs['active'] ?? true;

        return $policy;
    }

    // ── create() ─────────────────────────────────────────────────────────

    public function test_create_without_default_flag_skips_transaction(): void
    {
        $this->database->shouldNotReceive('transaction');
        $created = $this->policy();

        $this->policyRepository
            ->shouldReceive('create')
            ->once()
            ->with(['name' => 'Standard', 'site_id' => 10])
            ->andReturn($created);

        $result = $this->service->create(10, ['name' => 'Standard']);

        $this->assertSame($created, $result);
    }

    public function test_create_as_default_clears_existing_default_inside_transaction(): void
    {
        // Regression coverage: this call previously went through the
        // static Database::runTransaction() facade, which cannot be
        // mocked (static method mocking isn't allowed), so this behaviour
        // had zero test coverage before the fix.
        $created = $this->policy(['is_default' => true]);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn (callable $callback) => $callback());

        $this->policyRepository->shouldReceive('clearDefaultForSite')->once()->with(10);
        $this->policyRepository
            ->shouldReceive('create')
            ->once()
            ->with(['name' => 'New Default', 'is_default' => true, 'site_id' => 10])
            ->andReturn($created);

        $result = $this->service->create(10, ['name' => 'New Default', 'is_default' => true]);

        $this->assertSame($created, $result);
    }

    public function test_create_as_default_does_not_clear_or_create_when_transaction_fails(): void
    {
        $this->policyRepository->shouldNotReceive('clearDefaultForSite');
        $this->policyRepository->shouldNotReceive('create');

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andThrow(new \RuntimeException('could not open transaction'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('could not open transaction');

        $this->service->create(10, ['name' => 'New Default', 'is_default' => true]);
    }

    // ── update() ─────────────────────────────────────────────────────────

    public function test_update_throws_when_policy_not_found(): void
    {
        $this->policyRepository->shouldReceive('findForSite')->with(1, 10)->andReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Replacement policy not found.');

        $this->service->update(1, 10, ['name' => 'x']);
    }

    public function test_update_becoming_default_clears_others_inside_transaction(): void
    {
        $policy = $this->policy(['id' => 1, 'is_default' => false]);
        $updated = $this->policy(['id' => 1, 'is_default' => true]);

        $this->policyRepository->shouldReceive('findForSite')->with(1, 10)->andReturn($policy);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn (callable $callback) => $callback());

        $this->policyRepository->shouldReceive('clearDefaultForSite')->once()->with(10, 1);
        $this->policyRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, ['is_default' => true])
            ->andReturn($updated);

        $result = $this->service->update(1, 10, ['is_default' => true]);

        $this->assertSame($updated, $result);
    }

    public function test_update_not_becoming_default_skips_transaction(): void
    {
        $policy = $this->policy(['id' => 1, 'is_default' => false]);
        $updated = $this->policy(['id' => 1]);

        $this->policyRepository->shouldReceive('findForSite')->with(1, 10)->andReturn($policy);
        $this->database->shouldNotReceive('transaction');

        $this->policyRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, ['name' => 'Renamed'])
            ->andReturn($updated);

        $result = $this->service->update(1, 10, ['name' => 'Renamed']);

        $this->assertSame($updated, $result);
    }

    public function test_update_throws_when_underlying_update_returns_null(): void
    {
        $policy = $this->policy(['id' => 1, 'is_default' => false]);

        $this->policyRepository->shouldReceive('findForSite')->with(1, 10)->andReturn($policy);
        $this->policyRepository->shouldReceive('update')->once()->andReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Replacement policy not found.');

        $this->service->update(1, 10, ['name' => 'x']);
    }

    // ── deactivate() ─────────────────────────────────────────────────────

    public function test_deactivate_throws_when_it_is_the_only_active_default(): void
    {
        $policy = $this->policy(['id' => 1, 'is_default' => true, 'active' => true]);

        $this->policyRepository->shouldReceive('findForSite')->with(1, 10)->andReturn($policy);
        $this->policyRepository->shouldReceive('findOtherActiveDefault')->with(10, 1)->andReturn(null);
        $this->policyRepository->shouldNotReceive('update');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot deactivate the only active default policy for this site. Assign a new default first.');

        $this->service->deactivate(1, 10);
    }

    public function test_deactivate_succeeds_when_another_default_exists(): void
    {
        $policy = $this->policy(['id' => 1, 'is_default' => true, 'active' => true]);
        $other = $this->policy(['id' => 2, 'is_default' => true, 'active' => true]);
        $updated = $this->policy(['id' => 1, 'active' => false]);

        $this->policyRepository->shouldReceive('findForSite')->with(1, 10)->andReturn($policy);
        $this->policyRepository->shouldReceive('findOtherActiveDefault')->with(10, 1)->andReturn($other);
        $this->policyRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, ['active' => false])
            ->andReturn($updated);

        $result = $this->service->deactivate(1, 10);

        $this->assertSame($updated, $result);
    }

    public function test_deactivate_non_default_policy_skips_default_check(): void
    {
        $policy = $this->policy(['id' => 1, 'is_default' => false, 'active' => true]);
        $updated = $this->policy(['id' => 1, 'active' => false]);

        $this->policyRepository->shouldReceive('findForSite')->with(1, 10)->andReturn($policy);
        $this->policyRepository->shouldNotReceive('findOtherActiveDefault');
        $this->policyRepository->shouldReceive('update')->once()->andReturn($updated);

        $result = $this->service->deactivate(1, 10);

        $this->assertSame($updated, $result);
    }
}
