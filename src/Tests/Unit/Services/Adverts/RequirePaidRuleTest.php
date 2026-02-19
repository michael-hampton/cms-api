<?php

namespace App\Tests\Unit\Services\Adverts;

use App\Enums\Adverts\SuppressionReason;
use App\Models\Member;
use App\Services\Adverts\RequirePaidRule;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class RequirePaidRuleTest extends FunctionalTestCase
{
    public function testEvaluateReturnsHideWhenNoMember(): void
    {
        $rule = new RequirePaidRule();
        $decision = $rule->evaluate(null);

        $this->assertFalse($decision->shouldRender);
        $this->assertEquals(SuppressionReason::NOT_AUTHENTICATED, $decision->reason);
    }

    public function testEvaluateReturnsHideWhenMemberIsNotPaid(): void
    {
        $member = $this->createMock(Member::class);
        $member->method('isPaid')->willReturn(false);

        $rule = new RequirePaidRule();
        $decision = $rule->evaluate($member);

        $this->assertFalse($decision->shouldRender);
        $this->assertEquals(SuppressionReason::REQUIRES_PAID_MEMBERSHIP, $decision->reason);
    }

    public function testEvaluateReturnsShowWhenMemberIsPaid(): void
    {
        $member = $this->createMock(Member::class);
        $member->method('isPaid')->willReturn(true);

        $rule = new RequirePaidRule();
        $decision = $rule->evaluate($member);

        $this->assertTrue($decision->shouldRender);
    }
}
