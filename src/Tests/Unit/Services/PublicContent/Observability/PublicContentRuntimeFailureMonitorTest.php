<?php

namespace App\Tests\Unit\Services\PublicContent\Observability;

use App\Services\PublicContent\Observability\PublicContentRuntimeFailureMonitor;
use App\Services\PublicContent\Observability\PublicContentRuntimeFailureSignal;
use App\Services\PublicContent\Rollout\PublicContentKillSwitch;
use PHPUnit\Framework\TestCase;

final class PublicContentRuntimeFailureMonitorTest extends TestCase
{
    private string $statePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statePath = tempnam(sys_get_temp_dir(), 'pc-fail');
        @unlink($this->statePath);
    }

    protected function tearDown(): void
    {
        @unlink($this->statePath);
        parent::tearDown();
    }

    public function test_signal_is_named_and_provable_with_synthetic_failures(): void
    {
        $signal = new PublicContentRuntimeFailureSignal(windowSize: 20, threshold: 0.5);
        $kill = new PublicContentKillSwitch($this->statePath, 30);
        $monitor = new PublicContentRuntimeFailureMonitor($signal, $kill);

        self::assertSame(PublicContentRuntimeFailureSignal::NAME, $monitor->signalName());

        $proof = $monitor->proveWithSyntheticFailures(12, siteId: 5);

        self::assertSame(PublicContentRuntimeFailureSignal::NAME, $proof['signal']);
        self::assertTrue($proof['breached']);
        self::assertTrue($proof['killed']);
        self::assertTrue($kill->isSiteExcluded(5));
        self::assertGreaterThanOrEqual(0.5, $proof['failure_rate']);
    }

    public function test_successes_keep_rate_below_threshold(): void
    {
        $signal = new PublicContentRuntimeFailureSignal(windowSize: 20, threshold: 0.5);
        $kill = new PublicContentKillSwitch($this->statePath, 30);
        $monitor = new PublicContentRuntimeFailureMonitor($signal, $kill);

        for ($i = 0; $i < 20; $i++) {
            $monitor->observeSuccess(1);
        }

        self::assertFalse($signal->isBreached());
        self::assertFalse($kill->isSiteExcluded(1));
    }
}
