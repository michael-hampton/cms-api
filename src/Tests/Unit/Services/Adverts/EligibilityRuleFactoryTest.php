<?php

namespace App\Tests\Unit\Services\Adverts;

use App\Services\Adverts\EligibilityRuleFactory;
use App\Services\Adverts\MemberSegmentChecker;
use App\Services\Adverts\PlanMatchRule;
use App\Services\Adverts\RequirePaidRule;
use App\Services\Adverts\SegmentMatchRule;
use PHPUnit\Framework\TestCase;

class EligibilityRuleFactoryTest extends TestCase
{
    private MemberSegmentChecker $segmentChecker;
    private EligibilityRuleFactory $factory;

    public function testCreateFromArrayReturnsEmptyArrayWhenNoRules(): void
    {
        $rules = $this->factory->createFromArray([]);
        $this->assertEmpty($rules);
    }

    public function testCreateFromArrayHandlesRequirePaid(): void
    {
        $rules = $this->factory->createFromArray(['require_paid' => true]);
        $this->assertCount(1, $rules);
        $this->assertInstanceOf(RequirePaidRule::class, $rules[0]);
    }

    public function testCreateFromArrayHandlesPlan(): void
    {
        $rules = $this->factory->createFromArray(['plan' => 'premium']);
        $this->assertCount(1, $rules);
        $this->assertInstanceOf(PlanMatchRule::class, $rules[0]);
    }

    public function testCreateFromArrayHandlesSegment(): void
    {
        $rules = $this->factory->createFromArray(['segment' => 'gold']);
        $this->assertCount(1, $rules);
        $this->assertInstanceOf(SegmentMatchRule::class, $rules[0]);
    }

    public function testCreateFromArrayHandlesMultipleRules(): void
    {
        $rules = $this->factory->createFromArray([
            'require_paid' => true,
            'plan' => 'premium',
            'segment' => ['gold', 'silver']
        ]);
        $this->assertCount(3, $rules);
    }

    protected function setUp(): void
    {
        $this->segmentChecker = $this->createMock(MemberSegmentChecker::class);
        $this->factory = new EligibilityRuleFactory($this->segmentChecker);
    }
}
