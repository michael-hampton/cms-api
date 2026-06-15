<?php

namespace App\Tests\Unit\Services\Pricing;

use App\Services\Pricing\PeriodLabelFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PeriodLabelFormatterTest extends TestCase
{
    private PeriodLabelFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new PeriodLabelFormatter();
    }

    public function test_twenty_eight_days_compacts_to_four_weeks_but_keeps_raw_label(): void
    {
        $labels = $this->formatter->labels(28, 'day', 'en_GB', 'compact_equivalent');

        self::assertSame('28 days', $labels->raw);
        self::assertSame('4 weeks', $labels->display);
        self::assertSame('4 weeks', $labels->numeric);
        self::assertSame('four weeks', $labels->worded);
        self::assertSame('every 4 weeks', $labels->renewal);
    }

    #[DataProvider('nonCompactDayProvider')]
    public function test_other_day_counts_do_not_cascade_to_larger_units(int $days): void
    {
        $labels = $this->formatter->labels($days, 'day', 'en_GB', 'compact_equivalent');

        self::assertSame($days . ' days', $labels->raw);
        self::assertSame($days . ' days', $labels->display);
    }

    public static function nonCompactDayProvider(): array
    {
        return [
            'ten days' => [10],
            'thirty days' => [30],
            'three hundred and sixty five days' => [365],
        ];
    }

    #[DataProvider('singularProvider')]
    public function test_singular_renewal_labels_omit_the_leading_one(string $unit, string $expected): void
    {
        self::assertSame($expected, $this->formatter->labels(1, $unit)->renewal);
    }

    public static function singularProvider(): array
    {
        return [
            'year' => ['year', 'per year'],
            'month' => ['month', 'per month'],
            'day' => ['day', 'per day'],
        ];
    }

    public function test_plural_labels_use_numeric_customer_facing_copy(): void
    {
        $labels = $this->formatter->labels(2, 'month');

        self::assertSame('2 months', $labels->numeric);
        self::assertSame('two months', $labels->worded);
        self::assertSame('every 2 months', $labels->renewal);
    }

    public function test_unsupported_locale_keeps_safe_numeric_wording(): void
    {
        $labels = $this->formatter->labels(2, 'month', 'fr_FR');

        self::assertSame('2 months', $labels->numeric);
        self::assertSame('2 months', $labels->worded);
    }
}
