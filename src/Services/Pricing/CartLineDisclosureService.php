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
        $period = (string)($pricingFacts['period_description'] ?? $planFacts['billing_period'] ?? 'monthly');
        [$periodAmount, $periodUnit] = $this->periodSpec($period);
        $labels = $this->periodLabels->labels(
            $periodAmount,
            $periodUnit,
            $locale,
            (string)($pricingFacts['period_display_strategy'] ?? 'raw'),
        );

        $context = new PriceDisclosureContext(
            locale: $locale,
            currency: $currency,
            quantity: $quantity,
            itemAmountMinor: $lineAmountMinor,
            initialChargeAmountMinor: $introUnitMinor === null ? null : $introUnitMinor * $quantity,
            renewalAmountMinor: $renewalUnitMinor > 0 ? $renewalUnitMinor * $quantity : $lineAmountMinor,
            isRecurring: !((bool)($planFacts['is_one_time'] ?? false)),
            trialDays: $trialDays,
            introCycles: $introCycles,
            initialChargePeriodLabel: $introCycles === null ? null : $this->cyclesLabel($introCycles, $period),
            introPeriodLabel: $introCycles === null ? null : $this->cyclesLabel($introCycles, $period),
            renewalPeriodLabel: $labels->renewal,
            renewalDate: $this->renewalDate($item, $trialDays, $introCycles, $period),
            pricingLabel: $pricingFacts['label'] ?? null,
            badges: $this->badges($trialDays, $introUnitMinor, $introCycles),
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
        $start = !empty($item['options']['start_date'])
            ? new DateTimeImmutable((string)$item['options']['start_date'], new DateTimeZone('UTC'))
            : $this->clock->now()->setTimezone(new DateTimeZone('UTC'));

        if ($trialDays !== null) {
            return $start->add(new DateInterval('P' . $trialDays . 'D'));
        }

        return $start->add($this->billingInterval($period, $introCycles ?? 1));
    }

    private function billingInterval(string $period, int $cycles): DateInterval
    {
        return match (strtolower($period)) {
            'daily', 'day' => new DateInterval('P' . $cycles . 'D'),
            'weekly', 'week' => new DateInterval('P' . $cycles . 'W'),
            'quarterly', 'quarter' => new DateInterval('P' . ($cycles * 3) . 'M'),
            'yearly', 'annual', 'annually', 'year' => new DateInterval('P' . $cycles . 'Y'),
            default => new DateInterval('P' . $cycles . 'M'),
        };
    }

    private function periodSpec(string $period): array
    {
        return match (strtolower(trim($period))) {
            'daily', 'day' => [1, 'day'],
            'weekly', 'week' => [1, 'week'],
            'quarterly', 'quarter' => [3, 'month'],
            'yearly', 'annual', 'annually', 'year' => [1, 'year'],
            default => [1, 'month'],
        };
    }

    private function cyclesLabel(int $cycles, string $period): string
    {
        $unit = match (strtolower($period)) {
            'daily', 'day' => 'day',
            'weekly', 'week' => 'week',
            'quarterly', 'quarter' => 'quarter',
            'yearly', 'annual', 'annually', 'year' => 'year',
            default => 'month',
        };

        return $cycles . ' ' . $unit . ($cycles === 1 ? '' : 's');
    }

    private function badges(?int $trialDays, ?int $introUnitMinor, ?int $introCycles): array
    {
        $badges = [];
        if ($trialDays !== null) {
            $badges[] = $trialDays . '-day trial';
        }
        if ($introUnitMinor !== null && $introCycles !== null) {
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
