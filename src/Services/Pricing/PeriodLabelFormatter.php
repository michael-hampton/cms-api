<?php

namespace App\Services\Pricing;

use App\DTO\Pricing\PeriodLabels;

final class PeriodLabelFormatter
{
    public function labels(
        int $amount,
        string $unit,
        string $locale = 'en_GB',
        string $strategy = 'raw',
    ): PeriodLabels {
        $amount = max(1, $amount);
        $unit = $this->normalizeUnit($unit);
        $raw = $this->numericLabel($amount, $unit);

        [$displayAmount, $displayUnit] = $strategy === 'compact_equivalent'
            ? $this->compactEquivalent($amount, $unit)
            : [$amount, $unit];

        $display = $this->numericLabel($displayAmount, $displayUnit);
        $numeric = $display;
        $worded = $this->wordedLabel($displayAmount, $displayUnit, $locale);
        $renewal = $displayAmount === 1
            ? 'per ' . $this->singular($displayUnit)
            : 'every ' . $numeric;

        return new PeriodLabels(
            raw: $raw,
            display: $display,
            numeric: $numeric,
            worded: $worded,
            renewal: $renewal,
        );
    }

    private function compactEquivalent(int $amount, string $unit): array
    {
        if ($unit === 'day' && $amount === 28) {
            return [4, 'week'];
        }

        return [$amount, $unit];
    }

    private function numericLabel(int $amount, string $unit): string
    {
        return $amount . ' ' . $unit . ($amount === 1 ? '' : 's');
    }

    private function wordedLabel(int $amount, string $unit, string $locale): string
    {
        if (!str_starts_with(strtolower($locale), 'en')) {
            return $this->numericLabel($amount, $unit);
        }

        $number = match ($amount) {
            1 => 'one',
            2 => 'two',
            3 => 'three',
            4 => 'four',
            5 => 'five',
            6 => 'six',
            7 => 'seven',
            8 => 'eight',
            9 => 'nine',
            10 => 'ten',
            default => (string)$amount,
        };

        return $number . ' ' . $unit . ($amount === 1 ? '' : 's');
    }

    private function normalizeUnit(string $unit): string
    {
        return match (strtolower(trim($unit))) {
            'daily', 'days' => 'day',
            'weekly', 'weeks' => 'week',
            'monthly', 'months' => 'month',
            'quarterly', 'quarters' => 'quarter',
            'annual', 'annually', 'yearly', 'years' => 'year',
            default => strtolower(trim($unit)) ?: 'month',
        };
    }

    private function singular(string $unit): string
    {
        return rtrim($unit, 's');
    }
}
