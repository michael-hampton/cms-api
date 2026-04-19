<?php

namespace App\Tests\Unit\Services\Members;

use App\Framework\Database\Database;
use App\Models\Segment;
use App\Repositories\MemberInsights\SegmentRepository;
use App\Repositories\MemberInsights\SegmentRuleRepository;
use App\Services\MemberInsights\SegmentAdminService;
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

    private function makeSegment(int $id): Segment
    {
        $segment = Mockery::mock(Segment::class)->makePartial();
        $segment->id = $id;
        $segment->key = 'churning';
        $segment->name = 'Churning';
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
