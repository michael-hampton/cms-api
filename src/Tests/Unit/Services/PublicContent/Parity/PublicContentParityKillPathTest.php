<?php

namespace App\Tests\Unit\Services\PublicContent\Parity;

use App\Services\PublicContent\Observability\PublicContentRuntimeFailureSignal;
use App\Services\PublicContent\Parity\PublicContentParityKillPath;
use App\Services\PublicContent\Rollout\PublicContentKillSwitch;
use PHPUnit\Framework\TestCase;

/**
 * PublicContentRuntimeFailureSignal and PublicContentKillSwitch are both
 * final, so real (file-backed) instances are used instead of mocks.
 */
final class PublicContentParityKillPathTest extends TestCase
{
    private string $statePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statePath = sys_get_temp_dir() . '/parity-kill-path-' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->statePath)) {
            unlink($this->statePath);
        }
        parent::tearDown();
    }

    public function test_record_match_does_not_trip_the_kill_switch(): void
    {
        [$killPath, $killSwitch] = $this->build();

        $killPath->recordMatch();

        self::assertFalse($killSwitch->isGloballyDisabled());
    }

    public function test_a_single_mismatch_does_not_trip_the_kill_switch(): void
    {
        [$killPath, $killSwitch] = $this->build();

        $killPath->recordMismatch();

        self::assertFalse($killSwitch->isGloballyDisabled());
    }

    public function test_a_sustained_mismatch_rate_excludes_the_given_site(): void
    {
        [$killPath, $killSwitch] = $this->build(windowSize: 20, threshold: 0.05);

        for ($i = 0; $i < 20; $i++) {
            $killPath->recordMismatch(5);
        }

        self::assertTrue($killSwitch->isSiteExcluded(5));
        self::assertFalse($killSwitch->isGloballyDisabled());
    }

    public function test_a_sustained_mismatch_rate_with_no_site_disables_globally(): void
    {
        [$killPath, $killSwitch] = $this->build(windowSize: 20, threshold: 0.05);

        for ($i = 0; $i < 20; $i++) {
            $killPath->recordMismatch();
        }

        self::assertTrue($killSwitch->isGloballyDisabled());
    }

    /**
     * @return array{0: PublicContentParityKillPath, 1: PublicContentKillSwitch}
     */
    private function build(int $windowSize = 100, float $threshold = 0.05): array
    {
        $signal = new PublicContentRuntimeFailureSignal($windowSize, $threshold);
        $killSwitch = new PublicContentKillSwitch($this->statePath);

        return [new PublicContentParityKillPath($signal, $killSwitch), $killSwitch];
    }
}