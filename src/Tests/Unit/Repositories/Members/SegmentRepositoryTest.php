<?php

namespace App\Tests\Unit\Repositories\Members;

use App\Models\Segment;
use App\Repositories\MemberInsights\SegmentRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class SegmentRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private SegmentRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SegmentRepository();
    }

    public function test_find_with_rules_loads_relation(): void
    {
        $segment = Segment::create(['key' => 'test', 'name' => 'Test']);

        $result = $this->repository->findWithRules($segment->id);

        $this->assertNotNull($result);
        $this->assertRelationLoaded($result, 'rules');
    }

    public function test_exists_by_key_returns_true_when_exists(): void
    {
        Segment::create(['key' => 'test-key', 'name' => 'Test']);

        $this->assertTrue($this->repository->existsByKey('test-key'));
        $this->assertFalse($this->repository->existsByKey('other'));
    }

    public function test_get_active_ids_by_keys(): void
    {
        $s1 = Segment::create(['key' => 'k1', 'name' => 'N1', 'is_active' => true]);
        $s2 = Segment::create(['key' => 'k2', 'name' => 'N2', 'is_active' => false]);

        $result = $this->repository->getActiveIdsByKeys(['k1', 'k2']);

        $this->assertCount(1, $result);
        $this->assertEquals($s1->id, $result->get('k1'));
        $this->assertFalse($result->has('k2'));
    }
}
