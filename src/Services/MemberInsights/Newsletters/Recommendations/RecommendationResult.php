<?php

namespace App\Services\MemberInsights\Newsletters\Recommendations;

use App\Models\Newsletter;

final class RecommendationResult
{
    public function __construct(
        public readonly Newsletter $newsletter,
        public readonly string     $reason,
        public readonly int        $score,
    )
    {
    }
}