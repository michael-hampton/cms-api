<?php

namespace App\Services\Adverts;

class EligibilityRuleFactory
{
    public function __construct(
        private readonly MemberSegmentChecker $segmentChecker
    )
    {
    }

    public function createFromArray(array $rules): array
    {
        $ruleObjects = [];

        if ($rules['require_paid'] ?? false) {
            $ruleObjects[] = new RequirePaidRule();
        }

        if (isset($rules['plan'])) {
            $ruleObjects[] = new PlanMatchRule($rules['plan']);
        }

        if (isset($rules['segment'])) {
            $ruleObjects[] = new SegmentMatchRule(
                is_array($rules['segment']) ? $rules['segment'] : [$rules['segment']],
                $this->segmentChecker
            );
        }

        return $ruleObjects;
    }
}