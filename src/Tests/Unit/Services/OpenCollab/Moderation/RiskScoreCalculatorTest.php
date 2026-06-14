<?php

namespace App\Tests\Unit\Services\OpenCollab\Moderation;

use App\Enums\OpenCollab\RiskSeverity;
use App\Enums\OpenCollab\RiskSource;
use App\Enums\OpenCollab\RiskStatus;
use App\Enums\OpenCollab\RiskType;
use App\Framework\Support\Collection;
use App\Models\ContentRiskMarker;
use App\Services\OpenCollab\Moderation\RiskScoreCalculator;
use PHPUnit\Framework\TestCase;

class RiskScoreCalculatorTest extends TestCase
{
    private RiskScoreCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new RiskScoreCalculator();
    }

    public function test_no_markers_scores_zero(): void
    {
        $score = $this->calculator->calculate(Collection::make([]));

        $this->assertSame(0, $score);
    }

    public function test_single_low_marker_scores_ten(): void
    {
        $marker = $this->marker(RiskSeverity::Low);

        $score = $this->calculator->calculate(Collection::make([$marker]));

        $this->assertSame(10, $score);
    }

    public function test_persisted_string_severity_is_normalised(): void
    {
        $marker = $this->marker(RiskSeverity::High->value);

        $score = $this->calculator->calculate(Collection::make([$marker]));

        $this->assertSame(60, $score);
        $this->assertTrue(
            $this->calculator->hasBlockingRisk(Collection::make([$marker]))
        );
    }

    public function test_multiple_markers_sum_severity_scores(): void
    {
        $markers = Collection::make([
            $this->marker(RiskSeverity::High),   // 60
            $this->marker(RiskSeverity::Medium), // 30
            $this->marker(RiskSeverity::Low),    // 10
        ]);

        $score = $this->calculator->calculate($markers);

        $this->assertSame(100, $score);
    }

    public function test_critical_marker_scores_one_hundred(): void
    {
        $score = $this->calculator->calculate(Collection::make([$this->marker(RiskSeverity::Critical)]));

        $this->assertSame(100, $score);
    }

    public function test_has_blocking_risk_true_for_high_or_critical(): void
    {
        $this->assertTrue(
            $this->calculator->hasBlockingRisk(Collection::make([$this->marker(RiskSeverity::High)]))
        );

        $this->assertTrue(
            $this->calculator->hasBlockingRisk(Collection::make([$this->marker(RiskSeverity::Critical)]))
        );
    }

    public function test_has_blocking_risk_false_for_low_or_medium_only(): void
    {
        $markers = Collection::make([
            $this->marker(RiskSeverity::Low),
            $this->marker(RiskSeverity::Medium),
        ]);

        $this->assertFalse($this->calculator->hasBlockingRisk($markers));
    }

    public function test_has_blocking_risk_false_for_empty_collection(): void
    {
        $this->assertFalse($this->calculator->hasBlockingRisk(Collection::make([])));
    }

    private function marker(RiskSeverity|string $severity): ContentRiskMarker
    {
        $marker = \Mockery::mock(ContentRiskMarker::class)->makePartial();
        $marker->severity = $severity;
        $marker->risk_type = RiskType::Other;
        $marker->source = RiskSource::Moderator;
        $marker->status = RiskStatus::Open;

        return $marker;
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
