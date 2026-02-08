<?php

namespace App\Services\Adverts;

use App\Models\Member;

interface EligibilityRule
{
    public function evaluate(?Member $member): VisibilityDecision;
}