<?php

namespace App\Services\Pricing;

use App\DTO\Pricing\PriceDisclosureContext;
use DateTimeInterface;

final readonly class PriceDisclosureFormatter
{
    private const CURRENCY_SYMBOLS = [
        'GBP' => '£',
        'USD' => '$',
        'EUR' => '€',
        'PHP' => '₱',
        'AUD' => 'A$',
        'CAD' => 'C$',
        'JPY' => '¥',
    ];

    public function __construct(private PriceDisclosureTemplateResolver $templates)
    {
    }

    public function format(PriceDisclosureContext $context): array
    {
        $tokens = [
            ':item_price' => $this->formatMoney($context->itemAmountMinor, $context),
            ':initial_price' => $this->formatMoney($context->initialChargeAmountMinor ?? 0, $context),
            ':renewal_price' => $this->formatMoney($context->renewalAmountMinor ?? $context->itemAmountMinor, $context),
            ':trial_period_label' => $this->trialLabel($context->trialDays),
            ':initial_charge_period_label' => $context->initialChargePeriodLabel ?? '',
            ':intro_period_label' => $context->introPeriodLabel ?? '',
            ':renewal_period_label' => $context->renewalPeriodLabel ?? '',
            ':renewal_date' => $this->formatDate($context->renewalDate, $context->locale),
        ];

        if (!$context->isRecurring) {
            return [
                'main_line' => $this->render($context, 'one_time', $tokens),
                'renewal_line' => null,
                'label' => $context->pricingLabel,
                'badges' => $context->badges,
            ];
        }

        $mainKey = match (true) {
            $context->hasTrial() && $context->hasValidInitialCharge() => 'trial_with_start_charge',
            $context->hasTrial() => 'trial_without_start_charge',
            $context->hasValidInitialCharge() => 'introductory_price',
            default => 'subscription_without_trial',
        };

        return [
            'main_line' => $this->render($context, $mainKey, $tokens),
            'renewal_line' => $this->render(
                $context,
                $context->renewalDate === null ? 'renewal_without_date' : 'renewal_with_date',
                $tokens,
            ),
            'label' => $context->pricingLabel,
            'badges' => $context->badges,
        ];
    }

    private function render(PriceDisclosureContext $context, string $key, array $tokens): string
    {
        $template = $this->templates->resolve(
            $key,
            $context->locale,
            $context->experienceLanguageLines,
            $context->storeLanguageLines,
        );

        $rendered = trim(strtr($template, $tokens));

        if (preg_match('/:[a-z][a-z0-9_]*/i', $rendered) === 1) {
            $rendered = trim(strtr($this->templates->builtIn($key, $context->locale), $tokens));
        }

        return $rendered;
    }

    private function formatMoney(int $amountMinor, PriceDisclosureContext $context): string
    {
        $currency = strtoupper($context->currency);
        $fractionDigits = $this->currencyFractionDigits($currency);
        $divisor = 10 ** $fractionDigits;
        $amount = $amountMinor / $divisor;

        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter($context->locale, \NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency($amount, $currency);

            if ($formatted !== false) {
                return $formatted;
            }
        }

        $formattedAmount = number_format($amount, $fractionDigits, '.', ',');
        $symbol = self::CURRENCY_SYMBOLS[$currency] ?? null;

        return $symbol !== null
            ? $symbol . $formattedAmount
            : $currency . ' ' . $formattedAmount;
    }

    private function formatDate(?DateTimeInterface $date, string $locale): string
    {
        if ($date === null) {
            return '';
        }

        if (class_exists(\IntlDateFormatter::class)) {
            $formatter = new \IntlDateFormatter(
                $locale,
                \IntlDateFormatter::MEDIUM,
                \IntlDateFormatter::NONE,
                'UTC',
            );
            $formatted = $formatter->format($date);

            if ($formatted !== false) {
                return $formatted;
            }
        }

        return $date->format('j M Y');
    }

    private function trialLabel(?int $trialDays): string
    {
        if ($trialDays === null || $trialDays <= 0) {
            return '';
        }

        return $trialDays === 1 ? '1 day' : $trialDays . ' days';
    }

    private function currencyFractionDigits(string $currency): int
    {
        return in_array(
            strtoupper($currency),
            ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'],
            true,
        ) ? 0 : 2;
    }
}
