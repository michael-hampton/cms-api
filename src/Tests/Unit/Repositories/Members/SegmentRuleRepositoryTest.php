<?php

namespace App\Tests\Unit\Repositories\Members;

use App\Models\Segment;
use App\Models\SegmentRule;
use App\Repositories\MemberInsights\SegmentRuleRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class SegmentRuleRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private SegmentRuleRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SegmentRuleRepository();
    }

    public function test_find_by_segment_id_returns_ordered_rules(): void
    {
        $segment = Segment::create(['key' => 'test', 'name' => 'Test']);

        SegmentRule::create([
            'segment_id' => $segment->id,
            'field' => 'f1',
            'operator' => '=',
            'value' => json_encode('v1'),
            'sort_order' => 1
        ]);

        SegmentRule::create([
            'segment_id' => $segment->id,
            'field' => 'f0',
            'operator' => '=',
            'value' => json_encode('v0'),
            'sort_order' => 0
        ]);

        $result = $this->repository->findBySegmentId($segment->id);

        $this->assertCount(2, $result);
        $this->assertEquals('f0', $result->first()->field);
        $this->assertEquals('f1', $result->last()->field);
    }

    public function test_delete_by_segment_id(): void
    {
        $segment = Segment::create(['key' => 'test', 'name' => 'Test']);
        SegmentRule::create([
            'segment_id' => $segment->id,
            'field' => 'f1',
            'operator' => '=',
            'value' => json_encode('v1')
        ]);

        $this->repository->deleteBySegmentId($segment->id);

        $this->assertDatabaseMissing('segment_rules', ['segment_id' => $segment->id]);
    }

    public function test_create_many_for_segment(): void
    {
        $segment = Segment::create(['key' => 'test', 'name' => 'Test']);
        $rules = [
            ['field' => 'email', 'operator' => '=', 'value' => json_encode('test@test.com')],
            ['field' => 'status', 'operator' => '=', 'value' => json_encode('active'), 'boolean' => 'OR']
        ];

        $this->repository->createManyForSegment($segment->id, $rules);

        $this->assertDatabaseHas('segment_rules', ['segment_id' => $segment->id, 'field' => 'email']);
        $this->assertDatabaseHas('segment_rules', ['segment_id' => $segment->id, 'field' => 'status', 'boolean' => 'OR']);
    }
}
