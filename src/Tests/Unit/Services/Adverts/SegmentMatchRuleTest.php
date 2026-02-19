<?php

namespace App\Tests\Unit\Services\Adverts;

use App\Enums\Adverts\SuppressionReason;
use App\Models\Member;
use App\Services\Adverts\MemberSegmentChecker;
use App\Services\Adverts\SegmentMatchRule;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class SegmentMatchRuleTest extends FunctionalTestCase
{
    private MemberSegmentChecker $segmentChecker;

    public function testEvaluateReturnsHideWhenNoMember(): void
    {
        $rule = new SegmentMatchRule(['gold'], $this->segmentChecker);
        $decision = $rule->evaluate(null);

        $this->assertFalse($decision->shouldRender);
        $this->assertEquals(SuppressionReason::NOT_AUTHENTICATED, $decision->reason);
    }

    public function testEvaluateReturnsHideWhenSegmentMismatch(): void
    {
        $member = new Member(['segment' => 'silver']);
        $this->segmentChecker->method('isInAnySegment')->willReturn(false);

        $rule = new SegmentMatchRule(['gold'], $this->segmentChecker);
        $decision = $rule->evaluate($member);

        $this->assertFalse($decision->shouldRender);
        $this->assertEquals(SuppressionReason::SEGMENT_MISMATCH, $decision->reason);
    }

    public function testEvaluateReturnsShowWhenSegmentMatches(): void
    {
        $member = new Member(['segment' => 'gold']);
        $this->segmentChecker->method('isInAnySegment')->willReturn(true);

        $rule = new SegmentMatchRule(['gold'], $this->segmentChecker);
        $decision = $rule->evaluate($member);

        $this->assertTrue($decision->shouldRender);
    }

    protected function setUp(): void
    {
        $this->segmentChecker = $this->createMock(MemberSegmentChecker::class);
        parent::setUp();
    }
}
