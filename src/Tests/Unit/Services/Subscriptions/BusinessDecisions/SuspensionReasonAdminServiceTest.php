<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\BusinessDecisions;

use App\Models\SuspensionReason;
use App\Repositories\Subscriptions\BusinessDecisions\SuspensionReasonRepository;
use App\Services\Subscriptions\BusinessDecisions\SuspensionReasonAdminService;
use Mockery;
use PHPUnit\Framework\TestCase;

class SuspensionReasonAdminServiceTest extends TestCase
{
    private SuspensionReasonRepository $repository;
    private SuspensionReasonAdminService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(SuspensionReasonRepository::class);
        $this->service = new SuspensionReasonAdminService($this->repository);
    }

    public function test_create_trims_and_persists_a_unique_reason(): void
    {
        $created = $this->makeReason(10);
        $this->repository->shouldReceive('existsByCode')->once()->with('policy_violation')->andReturn(false);
        $this->repository->shouldReceive('create')->once()->with([
            'code' => 'policy_violation',
            'label' => 'Policy violation',
            'requires_note' => true,
            'is_active' => true,
            'sort_order' => 20,
        ])->andReturn($created);

        $result = $this->service->create([
            'code' => ' policy_violation ',
            'label' => ' Policy violation ',
            'requires_note' => true,
            'sort_order' => 20,
        ]);

        $this->assertSame($created, $result);
    }

    public function test_deactivate_marks_an_existing_reason_inactive(): void
    {
        $reason = $this->makeReason(10);
        $this->repository->shouldReceive('find')->once()->with(10)->andReturn($reason);
        $this->repository->shouldReceive('update')->once()->with(10, ['is_active' => false]);

        $this->service->deactivate(10);

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeReason(int $id): SuspensionReason
    {
        $reason = Mockery::mock(SuspensionReason::class)->makePartial();
        $reason->id = $id;

        return $reason;
    }
}
