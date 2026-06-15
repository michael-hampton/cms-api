<?php

namespace App\Services\Pricing;

use App\Contracts\ClockInterface;
use App\DTO\Pricing\PriceDisclosureContext;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final readonly class CartLineDisclosureService
{
    public function __construct(
        private PriceDisclosureFormatter $formatter,
        private PeriodLabelFormatter $periodLabels,
        private ClockInterface $clock,
    ) {
    }

    public function enrich(
        array $item,
        array $planFacts,
        string $locale,
        string $currency,
        array $experienceLanguageLines = [],
        array $storeLanguageLines = [],
        array $pricingFacts = [],
    ): array {
        if (empty($item['subscription_plan_id'])) {
            return $item;
        }

        $quantity = max(1, (int)($item['quantity'] ?? 1));
        $lineAmountMinor = isset($item['amount_minor'])
            ? (int)$item['amount_minor']
            : (int)round((float)($item['amount'] ?? $item['subtotal'] ?? 0) * 100);

        $renewalUnitMinor = (int)round((float)($pricingFacts['renewal_price'] ?? $pricingFacts['price'] ?? 0) * 100);
        $introUnitMinor = is_numeric($pricingFacts['intro_price'] ?? null)
            ? (int)round((float)$pricingFacts['intro_price'] * 100)
            : null;
        $trialDays = $this->positiveInt($pricingFacts['trial_days'] ?? $planFacts['trial_days'] ?? null);
        $introCycles = $this->positiveInt($pricingFacts['intro_cycles'] ?? null);
        $hasValidIntroCharge = $introUnitMinor !== null
            && $introUnitMinor > 0
            && $introCycles !== null;
        $period = (string)($pricingFacts['period_description'] ?? $planFacts['billing_period'] ?? 'monthly');
        [$periodAmount, $periodUnit] = $this->periodSpec($period);
        $labels = $this->periodLabels->labels(
            $periodAmount,
            $periodUnit,
            $locale,
            (string)($pricingFacts['period_display_strategy'] ?? 'raw'),
        );
        $introPeriodLabel = $hasValidIntroCharge
            ? $this->cyclesLabel($introCycles, $period)
            : null;

        $context = new PriceDisclosureContext(
            locale: $locale,
            currency: $currency,
            quantity: $quantity,
            itemAmountMinor: $lineAmountMinor,
            initialChargeAmountMinor: $hasValidIntroCharge ? $introUnitMinor * $quantity : null,
            renewalAmountMinor: $renewalUnitMinor > 0 ? $renewalUnitMinor * $quantity : $lineAmountMinor,
            isRecurring: !((bool)($planFacts['is_one_time'] ?? false)),
            trialDays: $trialDays,
            introCycles: $hasValidIntroCharge ? $introCycles : null,
            initialChargePeriodLabel: $introPeriodLabel,
            introPeriodLabel: $introPeriodLabel,
            renewalPeriodLabel: $labels->renewal,
            renewalDate: $this->renewalDate(
                $item,
                $trialDays,
                $hasValidIntroCharge ? $introCycles : null,
                $period,
            ),
            pricingLabel: $pricingFacts['label'] ?? null,
            badges: $this->badges($trialDays, $hasValidIntroCharge),
            experienceLanguageLines: $experienceLanguageLines,
            storeLanguageLines: $storeLanguageLines,
            rawPeriodLabel: $labels->raw,
            numericPeriodLabel: $labels->numeric,
            wordedPeriodLabel: $labels->worded,
        );

        $item['line_summary'] = $this->formatter->format($context);

        return $item;
    }

    private function renewalDate(array $item, ?int $trialDays, ?int $introCycles, string $period): DateTimeImmutable
    {
        $date = !empty($item['options']['start_date'])
            ? new DateTimeImmutable((string)$item['options']['start_date'], new DateTimeZone('UTC'))
            : $this->clock->now()->setTimezone(new DateTimeZone('UTC'));

        if ($trialDays !== null) {
            $date = $date->add(new DateInterval('P' . $trialDays . 'D'));
        }

        if ($introCycles !== null) {
            return $date->add($this->billingInterval($period, $introCycles));
        }

        if ($trialDays !== null) {
            return $date;
        }

        return $date->add($this->billingInterval($period, 1));
    }

    private function billingInterval(string $period, int $cycles): DateInterval
    {
        [$amount, $unit] = $this->periodSpec($period);
        $total = max(1, $amount * max(1, $cycles));

        return match ($unit) {
            'day' => new DateInterval('P' . $total . 'D'),
            'week' => new DateInterval('P' . $total . 'W'),
            'year' => new DateInterval('P' . $total . 'Y'),
            default => new DateInterval('P' . $total . 'M'),
        };
    }

    private function periodSpec(string $period): array
    {
        $normalized = strtolower(trim($period));

        if (preg_match('/^(\d+)\s*(day|week|month|year)s?$/', $normalized, $matches) === 1) {
            return [max(1, (int)$matches[1]), $matches[2]];
        }

        return match ($normalized) {
            'daily', 'day' => [1, 'day'],
            'weekly', 'week' => [1, 'week'],
            'quarterly', 'quarter' => [3, 'month'],
            'yearly', 'annual', 'annually', 'year' => [1, 'year'],
            default => [1, 'month'],
        };
    }

    private function cyclesLabel(int $cycles, string $period): string
    {
        [$amount, $unit] = $this->periodSpec($period);
        $total = max(1, $cycles) * $amount;

        return $total . ' ' . $unit . ($total === 1 ? '' : 's');
    }

    private function badges(?int $trialDays, bool $hasValidIntroCharge): array
    {
        $badges = [];
        if ($trialDays !== null) {
            $badges[] = $trialDays . '-day trial';
        }
        if ($hasValidIntroCharge) {
            $badges[] = 'Intro price';
        }
        return $badges;
    }

    private function positiveInt(mixed $value): ?int
    {
        $value = is_numeric($value) ? (int)$value : 0;
        return $value > 0 ? $value : null;
    }
}
