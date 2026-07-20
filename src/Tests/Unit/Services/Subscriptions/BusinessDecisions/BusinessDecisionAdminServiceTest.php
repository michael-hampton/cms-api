<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\BusinessDecisions;

use App\Enums\Subscriptions\BusinessDecisions\BusinessDecisionCategoryEnum;
use App\Framework\Database\Database;
use App\Models\BusinessDecision;
use App\Repositories\Subscriptions\BusinessDecisions\BusinessDecisionAssignmentRepository;
use App\Repositories\Subscriptions\BusinessDecisions\BusinessDecisionRepository;
use App\Repositories\Subscriptions\BusinessDecisions\CancellationReasonPolicyRepository;
use App\Repositories\Subscriptions\BusinessDecisions\CancellationReasonRepository;
use App\Repositories\Subscriptions\BusinessDecisions\RefundReasonPolicyRepository;
use App\Repositories\Subscriptions\BusinessDecisions\RefundReasonRepository;
use App\Repositories\Subscriptions\BusinessDecisions\SuspensionPolicyRepository;
use App\Services\Subscriptions\BusinessDecisions\BusinessDecisionAdminService;
use InvalidArgumentException;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class BusinessDecisionAdminServiceTest extends TestCase
{
    private $decisionRepository;
    private $assignmentRepository;
    private $reasonRepository;
    private $reasonPolicyRepository;
    private $refundReasonRepository;
    private $refundReasonPolicyRepository;
    private $suspensionPolicyRepository;
    private $database;
    private BusinessDecisionAdminService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->decisionRepository = m::mock(BusinessDecisionRepository::class);
        $this->assignmentRepository = m::mock(BusinessDecisionAssignmentRepository::class);
        $this->reasonRepository = m::mock(CancellationReasonRepository::class);
        $this->reasonPolicyRepository = m::mock(CancellationReasonPolicyRepository::class);
        $this->refundReasonRepository = m::mock(RefundReasonRepository::class);
        $this->refundReasonPolicyRepository = m::mock(RefundReasonPolicyRepository::class);
        $this->suspensionPolicyRepository = m::mock(SuspensionPolicyRepository::class);
        $this->database = m::mock(Database::class);

        $this->service = new BusinessDecisionAdminService(
            $this->decisionRepository,
            $this->assignmentRepository,
            $this->reasonRepository,
            $this->reasonPolicyRepository,
            $this->refundReasonRepository,
            $this->refundReasonPolicyRepository,
            $this->suspensionPolicyRepository,
            $this->database,
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    private function makeDecision(int $id, BusinessDecisionCategoryEnum $category = BusinessDecisionCategoryEnum::CANCELLATIONS): BusinessDecision
    {
        $decision = m::mock(BusinessDecision::class)->makePartial();
        $decision->id = $id;
        $decision->category = $category;

        return $decision;
    }

    /**
     * Creating a decision as is_default must clear any other default in
     * the same category — 2 writes (create + clear), inside a
     * transaction boundary per the coding contract.
     */
    public function test_creating_a_default_decision_clears_the_previous_default_in_a_transaction(): void
    {
        $created = $this->makeDecision(10);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $this->decisionRepository->shouldReceive('create')
            ->once()
            ->andReturn($created);

        $this->decisionRepository->shouldReceive('clearDefaultForCategory')
            ->once()
            ->with(BusinessDecisionCategoryEnum::CANCELLATIONS->value, 10);

        $result = $this->service->create([
            'category' => 'cancellations',
            'name' => 'New Default',
            'is_default' => true,
        ]);

        $this->assertSame($created, $result);
    }

    public function test_creating_a_non_default_decision_never_touches_other_defaults(): void
    {
        $created = $this->makeDecision(11);

        $this->database->shouldReceive('transaction')->andReturnUsing(fn ($callback) => $callback());
        $this->decisionRepository->shouldReceive('create')->once()->andReturn($created);
        $this->decisionRepository->shouldNotReceive('clearDefaultForCategory');

        $this->service->create([
            'category' => 'cancellations',
            'name' => 'Not Default',
        ]);

        $this->assertTrue(true);
    }

    public function test_create_rejects_an_unknown_category(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->create([
            'category' => 'not_a_real_category',
            'name' => 'Whatever',
        ]);
    }

    public function test_assign_rejects_an_unknown_assignable_type(): void
    {
        $decision = $this->makeDecision(5);
        $this->decisionRepository->shouldReceive('find')->with(5)->andReturn($decision);

        $this->expectException(InvalidArgumentException::class);

        $this->service->assign('publisher', 1, 5);
    }
}