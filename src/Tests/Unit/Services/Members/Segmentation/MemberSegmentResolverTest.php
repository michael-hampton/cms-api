<?php

namespace App\Tests\Unit\Services\Members\Segmentation;

use App\Framework\Support\Collection;
use App\Models\Segment;
use App\Repositories\Members\SegmentRepository;
use App\Services\Members\Segmentation\MemberSegmentResolver;
use App\Services\Members\Segmentation\SegmentRuleEvaluator;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class MemberSegmentResolverTest extends TestCase
{
    private SegmentRuleEvaluator|MockInterface $evaluator;
    private SegmentRepository|MockInterface $segmentRepository;
    private MemberSegmentResolver $resolver;

    public function test_returns_empty_array_when_no_segments_match(): void
    {
        $this->mockActiveSegments([
            $this->makeSegment('churning', rules: []),
        ]);

        $this->evaluator
            ->shouldReceive('matches')
            ->once()
            ->andReturn(false);

        $result = $this->resolver->resolve(['trends' => ['7d_change' => 5]]);

        $this->assertSame([], $result);
    }

    /**
     * Replace Segment::with()->where()->get() with our controlled list.
     * We use the Eloquent static mock approach via partial binding.
     */
    private function mockActiveSegments(array $segments): void
    {
        $this->segmentRepository
            ->shouldReceive('getActiveWithRules')
            ->once()
            ->andReturn(new Collection($segments));
    }

    private function makeSegment(string $key, array $rules): Segment
    {
        $segment = Mockery::mock(Segment::class)->makePartial();
        $segment->key = $key;
        $ruleModels = collect($rules)->map(function ($def) {
            $rule = new \stdClass();
            $rule->field = $def['field'] ?? 'scores.activity_score';
            $rule->operator = $def['operator'] ?? '>';
            $rule->value = $def['value'] ?? 0;
            $rule->boolean = $def['boolean'] ?? 'AND';
            return $rule;
        });
        $segment->setRelation('rules', $ruleModels);
        return $segment;
    }

    public function test_returns_matched_segment_keys(): void
    {
        $this->mockActiveSegments([
            $this->makeSegment('churning', rules: []),
            $this->makeSegment('highly_active', rules: []),
        ]);

        $this->evaluator
            ->shouldReceive('matches')
            ->twice()
            ->andReturn(true, false);

        $result = $this->resolver->resolve(['trends' => ['7d_change' => -30]]);

        $this->assertSame(['churning'], $result);
    }

    public function test_multiple_segments_can_match_simultaneously(): void
    {
        $this->mockActiveSegments([
            $this->makeSegment('churning', rules: []),
            $this->makeSegment('lurker', rules: []),
        ]);

        $this->evaluator
            ->shouldReceive('matches')
            ->twice()
            ->andReturn(true, true);

        $result = $this->resolver->resolve([
            'trends' => ['7d_change' => -30],
            'flags' => ['lurker_profile'],
        ]);

        $this->assertSame(['churning', 'lurker'], $result);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function test_returns_empty_array_for_empty_profile(): void
    {
        $this->mockActiveSegments([
            $this->makeSegment('highly_active', rules: []),
        ]);

        $this->evaluator
            ->shouldReceive('matches')
            ->once()
            ->andReturn(false);

        $result = $this->resolver->resolve([]);

        $this->assertSame([], $result);
    }

    protected function setUp(): void
    {
        $this->evaluator = Mockery::mock(SegmentRuleEvaluator::class);
        $this->segmentRepository = Mockery::mock(SegmentRepository::class);
        $this->resolver = new MemberSegmentResolver($this->evaluator, $this->segmentRepository);
    }
}
