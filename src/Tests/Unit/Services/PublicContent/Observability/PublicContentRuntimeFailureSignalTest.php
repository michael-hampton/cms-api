<?php

namespace App\Tests\Unit\Services\PublicContent\Observability;

use App\Services\PublicContent\Observability\PublicContentRuntimeFailureSignal;
use PHPUnit\Framework\TestCase;

final class PublicContentRuntimeFailureSignalTest extends TestCase
{
    public function test_name_returns_the_signal_constant(): void
    {
        $signal = new PublicContentRuntimeFailureSignal();

        self::assertSame('public_content.runtime_failure_rate', $signal->name());
    }

    public function test_failure_rate_is_zero_with_no_samples(): void
    {
        $signal = new PublicContentRuntimeFailureSignal();

        self::assertSame(0.0, $signal->failureRate());
        self::assertSame(0, $signal->sampleCount());
    }

    public function test_failure_rate_reflects_the_mix_of_successes_and_failures(): void
    {
        $signal = new PublicContentRuntimeFailureSignal(windowSize: 10, threshold: 0.5);

        $signal->recordSuccess();
        $signal->recordSuccess();
        $signal->recordSuccess();
        $signal->recordFailure();

        self::assertSame(4, $signal->sampleCount());
        self::assertSame(0.25, $signal->failureRate());
    }

    public function test_the_window_only_keeps_the_most_recent_samples(): void
    {
        $signal = new PublicContentRuntimeFailureSignal(windowSize: 3, threshold: 0.5);

        $signal->recordFailure();
        $signal->recordSuccess();
        $signal->recordSuccess();
        $signal->recordSuccess();

        self::assertSame(3, $signal->sampleCount());
        self::assertSame(0.0, $signal->failureRate());
    }

    public function test_is_breached_is_false_before_the_minimum_sample_size_is_reached(): void
    {
        $signal = new PublicContentRuntimeFailureSignal(windowSize: 100, threshold: 0.05);

        // Minimum sample floor is 20% of window size = 20 samples.
        for ($i = 0; $i < 19; $i++) {
            $signal->recordFailure();
        }

        self::assertFalse($signal->isBreached());
    }

    public function test_is_breached_is_true_once_enough_samples_exceed_the_threshold(): void
    {
        $signal = new PublicContentRuntimeFailureSignal(windowSize: 100, threshold: 0.05);

        for ($i = 0; $i < 20; $i++) {
            $signal->recordFailure();
        }

        self::assertTrue($signal->isBreached());
    }

    public function test_is_breached_is_false_when_failure_rate_is_under_threshold(): void
    {
        $signal = new PublicContentRuntimeFailureSignal(windowSize: 20, threshold: 0.5);

        for ($i = 0; $i < 20; $i++) {
            $signal->recordSuccess();
        }
        $signal->recordFailure();

        self::assertFalse($signal->isBreached());
    }

    public function test_inject_synthetic_failure_counts_as_a_failure(): void
    {
        $signal = new PublicContentRuntimeFailureSignal(windowSize: 10, threshold: 0.05);

        $signal->injectSyntheticFailure();

        self::assertSame(1, $signal->sampleCount());
        self::assertSame(1.0, $signal->failureRate());
    }

    public function test_threshold_returns_the_configured_value(): void
    {
        $signal = new PublicContentRuntimeFailureSignal(windowSize: 10, threshold: 0.1);

        self::assertSame(0.1, $signal->threshold());
    }
}