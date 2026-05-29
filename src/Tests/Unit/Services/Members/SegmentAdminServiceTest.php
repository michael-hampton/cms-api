<?php

namespace App\Tests\Unit\Services\Members;

use App\Enums\Member\SegmentSubjectType;
use App\Framework\Database\Database;
use App\Models\Segment;
use App\Repositories\MemberInsights\SegmentRepository;
use App\Repositories\MemberInsights\SegmentRuleRepository;
use App\Services\Members\SegmentAdminService;
use Mockery;
use PHPUnit\Framework\TestCase;

class SegmentAdminServiceTest extends TestCase
{
    private SegmentRepository $segmentRepository;
    private SegmentRuleRepository $ruleRepository;
    private Database $databaseMock;
    private SegmentAdminService $service;

    public function test_create_persists_segment_and_rules(): void
    {
        $payload = $this->payload();
        $segment = $this->makeSegment(2);

        $this->segmentRepository->allows('existsByKey')->with('churning')->andReturn(false);
        $this->databaseMock->allows('transaction')->andReturnUsing(fn(callable $callback) => $callback());
        $this->segmentRepository->allows('create')->andReturn($segment);
        $this->ruleRepository->expects('createManyForSegment')->with(2, $payload['rules'])->once();
        $this->segmentRepository->allows('findWithRules')->with(2)->andReturn($segment);

        $result = $this->service->create($payload);

        $this->assertSame($segment, $result);
    }

    private function payload(): array
    {
        return [
            'key' => 'churning',
            'name' => 'Churning',
            'rules' => [
                ['field' => 'scores.activity_score', 'operator' => '<=', 'value' => 10, 'boolean' => 'AND'],
            ],
        ];
    }

    private function makeSegment(int $id, ?SegmentSubjectType $subjectType = null): Segment
    {
        $segment = Mockery::mock(Segment::class)->makePartial();
        $segment->id           = $id;
        $segment->subject_type = $subjectType;
        $segment->priority     = 100;

        return $segment;
    }

    public function test_create_throws_on_duplicate_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->segmentRepository->allows('existsByKey')->andReturn(true);

        $this->service->create($this->payload());
    }

    public function test_update_replaces_rules_when_provided(): void
    {
        $segment = $this->makeSegment(8);

        $this->segmentRepository->allows('findWithRules')->with(8)->andReturn($segment);
        $this->segmentRepository->allows('existsByKey')->with('power_users', 8)->andReturn(false);
        $this->databaseMock->allows('transaction')->andReturnUsing(fn(callable $callback) => $callback());
        $this->segmentRepository->expects('update')->once();
        $this->ruleRepository->expects('deleteBySegmentId')->with(8)->once();
        $this->ruleRepository->expects('createManyForSegment')->with(8, $this->payload()['rules'])->once();

        $this->service->update(8, [
            'key' => 'power_users',
            'rules' => $this->payload()['rules'],
        ]);

        $this->assertTrue(true);
    }

    public function test_update_throws_on_invalid_operator(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $segment = $this->makeSegment(8);
        $this->segmentRepository->allows('findWithRules')->with(8)->andReturn($segment);

        $this->service->update(8, [
            'rules' => [['field' => 'score', 'operator' => 'bad', 'value' => 1]],
        ]);
    }

    public function test_it_defaults_to_member_subject_type_when_not_provided(): void
    {
        $segment = $this->makeSegment(1, SegmentSubjectType::Member);

        $this->segmentRepository->allows('existsByKey')->andReturn(false);
        $this->databaseMock->allows('transaction')->andReturnUsing(fn(callable $cb) => $cb());
        $this->segmentRepository->allows('create')
            ->with(Mockery::on(fn(array $data) => $data['subject_type'] === SegmentSubjectType::Member->value))
            ->andReturn($segment);
        $this->ruleRepository->allows('createManyForSegment');
        $this->segmentRepository->allows('findWithRules')->andReturn($segment);

        $result = $this->service->create([
            'key'  => 'lurker',
            'name' => 'Lurker',
            // no subject_type supplied
        ]);

        $this->assertSame($segment, $result);
    }

    public function test_it_creates_member_segment_explicitly(): void
    {
        $segment = $this->makeSegment(2, SegmentSubjectType::Member);

        $this->segmentRepository->allows('existsByKey')->andReturn(false);
        $this->databaseMock->allows('transaction')->andReturnUsing(fn(callable $cb) => $cb());
        $this->segmentRepository->allows('create')
            ->with(Mockery::on(fn(array $data) => $data['subject_type'] === 'member'))
            ->andReturn($segment);
        $this->ruleRepository->allows('createManyForSegment');
        $this->segmentRepository->allows('findWithRules')->andReturn($segment);

        $result = $this->service->create([
            'key'          => 'churning',
            'name'         => 'Churning',
            'subject_type' => 'member',
        ]);

        $this->assertSame($segment, $result);
    }

    public function test_it_creates_subscription_segment(): void
    {
        $segment = $this->makeSegment(3, SegmentSubjectType::Subscription);

        $this->segmentRepository->allows('existsByKey')->andReturn(false);
        $this->databaseMock->allows('transaction')->andReturnUsing(fn(callable $cb) => $cb());
        $this->segmentRepository->allows('create')
            ->with(Mockery::on(fn(array $data) => $data['subject_type'] === 'subscription'))
            ->andReturn($segment);
        $this->ruleRepository->allows('createManyForSegment');
        $this->segmentRepository->allows('findWithRules')->andReturn($segment);

        $result = $this->service->create([
            'key'          => 'renewal_dd',
            'name'         => 'Renewal DD',
            'subject_type' => 'subscription',
        ]);

        $this->assertSame($segment, $result);
    }

    public function test_it_creates_plan_segment(): void
    {
        $segment = $this->makeSegment(4, SegmentSubjectType::Plan);

        $this->segmentRepository->allows('existsByKey')->andReturn(false);
        $this->databaseMock->allows('transaction')->andReturnUsing(fn(callable $cb) => $cb());
        $this->segmentRepository->allows('create')
            ->with(Mockery::on(fn(array $data) => $data['subject_type'] === 'plan'))
            ->andReturn($segment);
        $this->ruleRepository->allows('createManyForSegment');
        $this->segmentRepository->allows('findWithRules')->andReturn($segment);

        $result = $this->service->create([
            'key'          => 'price_rise',
            'name'         => 'Price Rise Plan',
            'subject_type' => 'plan',
        ]);

        $this->assertSame($segment, $result);
    }

    public function test_it_defaults_priority_to_100(): void
    {
        $segment = $this->makeSegment(5, SegmentSubjectType::Member);

        $this->segmentRepository->allows('existsByKey')->andReturn(false);
        $this->databaseMock->allows('transaction')->andReturnUsing(fn(callable $cb) => $cb());
        $this->segmentRepository->allows('create')
            ->with(Mockery::on(fn(array $data) => $data['priority'] === 100))
            ->andReturn($segment);
        $this->ruleRepository->allows('createManyForSegment');
        $this->segmentRepository->allows('findWithRules')->andReturn($segment);

        $this->service->create(['key' => 'x', 'name' => 'X']);

        $this->addToAssertionCount(1); // assertion in Mockery expectation above
    }

    public function test_it_persists_configured_priority(): void
    {
        $segment = $this->makeSegment(6, SegmentSubjectType::Subscription);

        $this->segmentRepository->allows('existsByKey')->andReturn(false);
        $this->databaseMock->allows('transaction')->andReturnUsing(fn(callable $cb) => $cb());
        $this->segmentRepository->allows('create')
            ->with(Mockery::on(fn(array $data) => $data['priority'] === 10))
            ->andReturn($segment);
        $this->ruleRepository->allows('createManyForSegment');
        $this->segmentRepository->allows('findWithRules')->andReturn($segment);

        $this->service->create([
            'key'          => 'high_priority_dd',
            'name'         => 'High Priority DD',
            'subject_type' => 'subscription',
            'priority'     => 10,
        ]);

        $this->addToAssertionCount(1);
    }

    public function test_it_throws_on_invalid_subject_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/subject_type/');

        $this->segmentRepository->allows('existsByKey')->andReturn(false);

        $this->service->create([
            'key'          => 'bad',
            'name'         => 'Bad',
            'subject_type' => 'invoice',  // not a valid enum value
        ]);
    }

    public function test_update_throws_on_invalid_subject_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/subject_type/');

        $segment = $this->makeSegment(7, SegmentSubjectType::Member);
        $this->segmentRepository->allows('findWithRules')->andReturn($segment);
        $this->segmentRepository->allows('existsByKey')->andReturn(false);

        $this->service->update(7, ['subject_type' => 'bad_value']);
    }

    protected function setUp(): void
    {
        $this->segmentRepository = Mockery::mock(SegmentRepository::class);
        $this->ruleRepository = Mockery::mock(SegmentRuleRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->service = new SegmentAdminService($this->segmentRepository, $this->ruleRepository, $this->databaseMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}
