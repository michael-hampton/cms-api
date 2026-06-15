<?php

namespace App\Services\Pricing;

use App\DTO\Pricing\PriceDisclosureContext;
use DateTimeInterface;
use NumberFormatter;

final class PriceDisclosureFormatter
{
    private const DEFAULT_TEMPLATES = [
        'en' => [
            'one_time' => '{amount}',
            'recurring' => '{amount} {period}',
            'trial' => '{trial_days}-day free trial, then {amount} {period}',
            'renewal' => 'Renews {renewal_date} at {amount} {period}',
            'renewal_without_date' => 'Renews at {amount} {period}',
        ],
    ];

    public function format(PriceDisclosureContext $context): array
    {
        $tokens = [
            '{amount}' => $this->formatMoney($context),
            '{period}' => $this->formatPeriod($context),
            '{trial_days}' => (string)max(0, (int)$context->trialDays),
            '{renewal_date}' => $this->formatDate($context->renewalDate, $context->locale),
        ];

        if (!$context->isRecurring) {
            return [
                'main_line' => $this->render($context, 'one_time', $tokens),
                'renewal_line' => null,
            ];
        }

        $mainTemplate = $context->trialDays !== null && $context->trialDays > 0
            ? 'trial'
            : 'recurring';

        $renewalTemplate = $context->renewalDate !== null
            ? 'renewal'
            : 'renewal_without_date';

        return [
            'main_line' => $this->render($context, $mainTemplate, $tokens),
            'renewal_line' => $this->render($context, $renewalTemplate, $tokens),
        ];
    }

    private function render(PriceDisclosureContext $context, string $key, array $tokens): string
    {
        $template = $context->copyOverrides[$key]
            ?? $this->defaultTemplate($context->locale, $key);

        return trim(strtr($template, $tokens));
    }

    private function defaultTemplate(string $locale, string $key): string
    {
        $language = strtolower(substr(str_replace('_', '-', $locale), 0, 2));

        return self::DEFAULT_TEMPLATES[$language][$key]
            ?? self::DEFAULT_TEMPLATES['en'][$key];
    }

    private function formatMoney(PriceDisclosureContext $context): string
    {
        $formatter = new NumberFormatter($context->locale, NumberFormatter::CURRENCY);
        $amount = $context->lineAmountMinor() / (10 ** $this->currencyFractionDigits($context->currency));
        $formatted = $formatter->formatCurrency($amount, strtoupper($context->currency));

        return $formatted !== false
            ? $formatted
            : strtoupper($context->currency) . ' ' . number_format($amount, 2, '.', ',');
    }

    private function formatPeriod(PriceDisclosureContext $context): string
    {
        $period = strtolower(trim($context->billingPeriod));
        $interval = max(1, $context->billingInterval);
        $strategy = $context->formatterSettings['period_display_strategy'] ?? 'normalized';

        if ($strategy === 'source') {
            return $interval === 1 ? 'per ' . $period : 'every ' . $interval . ' ' . $period;
        }

        $singular = match ($period) {
            'day', 'daily' => 'day',
            'week', 'weekly' => 'week',
            'month', 'monthly' => 'month',
            'quarter', 'quarterly' => '3 months',
            'year', 'annual', 'annually', 'yearly' => 'year',
            default => $period !== '' ? $period : 'month',
        };

        if ($interval === 1) {
            return 'per ' . $singular;
        }

        return 'every ' . $interval . ' ' . $this->pluralize($singular);
    }

    private function pluralize(string $period): string
    {
        return ctype_digit($period[0] ?? '') || str_ends_with($period, 's')
            ? $period
            : $period . 's';
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
            );
            $formatted = $formatter->format($date);

            if ($formatted !== false) {
                return $formatted;
            }
        }

        return $date->format('j M Y');
    }

    private function currencyFractionDigits(string $currency): int
    {
        return in_array(strtoupper($currency), ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'], true)
            ? 0
            : 2;
    }
}
