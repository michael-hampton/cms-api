<?php

namespace App\Tests\Unit\Services\OpenCollab\Moderation;

use App\Services\OpenCollab\Moderation\ModerationPriorityCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ModerationPriorityCalculatorTest extends TestCase
{
    private ModerationPriorityCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ModerationPriorityCalculator();
    }

    public function test_no_risk_recent_submission_no_schedule_scores_zero(): void
    {
        $now = new DateTimeImmutable('2026-06-13 12:00:00');
        $submittedAt = new DateTimeImmutable('2026-06-13 10:00:00'); // 2 hours ago

        $score = $this->calculator->calculate(
            riskScore: 0,
            scheduledPublishAt: null,
            submittedAt: $submittedAt,
            now: $now,
        );

        $this->assertSame(0, $score);
    }

    public function test_high_risk_article_scores_risk_score(): void
    {
        $now = new DateTimeImmutable('2026-06-13 12:00:00');
        $submittedAt = new DateTimeImmutable('2026-06-13 10:00:00');

        $score = $this->calculator->calculate(
            riskScore: 60,
            scheduledPublishAt: null,
            submittedAt: $submittedAt,
            now: $now,
        );

        $this->assertSame(60, $score);
    }

    public function test_scheduled_within_24_hours_adds_forty(): void
    {
        $now = new DateTimeImmutable('2026-06-13 12:00:00');
        $submittedAt = new DateTimeImmutable('2026-06-13 10:00:00');
        $scheduledAt = $now->modify('+10 hours');

        $score = $this->calculator->calculate(
            riskScore: 0,
            scheduledPublishAt: $scheduledAt,
            submittedAt: $submittedAt,
            now: $now,
        );

        $this->assertSame(40, $score);
    }

    public function test_scheduled_more_than_24_hours_away_adds_nothing(): void
    {
        $now = new DateTimeImmutable('2026-06-13 12:00:00');
        $submittedAt = new DateTimeImmutable('2026-06-13 10:00:00');
        $scheduledAt = $now->modify('+48 hours');

        $score = $this->calculator->calculate(
            riskScore: 0,
            scheduledPublishAt: $scheduledAt,
            submittedAt: $submittedAt,
            now: $now,
        );

        $this->assertSame(0, $score);
    }

    public function test_old_article_adds_age_boost(): void
    {
        $now = new DateTimeImmutable('2026-06-13 12:00:00');
        $submittedAt = $now->modify('-49 hours');

        $score = $this->calculator->calculate(
            riskScore: 0,
            scheduledPublishAt: null,
            submittedAt: $submittedAt,
            now: $now,
        );

        $this->assertSame(20, $score);
    }

    public function test_article_at_exactly_48_hours_does_not_get_age_boost(): void
    {
        $now = new DateTimeImmutable('2026-06-13 12:00:00');
        $submittedAt = $now->modify('-48 hours');

        $score = $this->calculator->calculate(
            riskScore: 0,
            scheduledPublishAt: null,
            submittedAt: $submittedAt,
            now: $now,
        );

        $this->assertSame(0, $score);
    }

    public function test_manual_priority_boost_is_additive(): void
    {
        $now = new DateTimeImmutable('2026-06-13 12:00:00');
        $submittedAt = new DateTimeImmutable('2026-06-13 10:00:00');

        $score = $this->calculator->calculate(
            riskScore: 30,
            scheduledPublishAt: null,
            submittedAt: $submittedAt,
            now: $now,
            manualPriorityBoost: 15,
        );

        $this->assertSame(45, $score);
    }

    public function test_all_factors_combine(): void
    {
        $now = new DateTimeImmutable('2026-06-13 12:00:00');
        $submittedAt = $now->modify('-50 hours'); // age boost +20
        $scheduledAt = $now->modify('+1 hour');   // scheduled boost +40

        $score = $this->calculator->calculate(
            riskScore: 60,        // high risk
            scheduledPublishAt: $scheduledAt,
            submittedAt: $submittedAt,
            now: $now,
            manualPriorityBoost: 5,
        );

        $this->assertSame(60 + 40 + 20 + 5, $score);
    }
}