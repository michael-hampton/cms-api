<?php

namespace App\Framework\Support;

use NumberFormatter;

class Number
{
    /**
     * Format a number with grouped thousands
     */
    public static function format(float $number, int $precision = 0, string $decimalSeparator = '.', string $thousandsSeparator = ','): string
    {
        return number_format($number, $precision, $decimalSeparator, $thousandsSeparator);
    }

    /**
     * Convert a number to its percentage equivalent
     */
    public static function percentage(float $number, int $precision = 0, int $maxPrecision = null): string
    {
        $percentage = $number * 100;

        if ($maxPrecision !== null) {
            $precision = min($precision, $maxPrecision);
        }

        return static::format($percentage, $precision) . '%';
    }

    /**
     * Convert a number to its currency format
     */
    public static function currency(float $number, string $currency = 'USD', string $locale = 'en_US'): string
    {
        if (class_exists('NumberFormatter')) {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            return $formatter->formatCurrency($number, $currency);
        }

        // Fallback for when NumberFormatter is not available
        $symbol = static::getCurrencySymbol($currency);
        return $symbol . static::format($number, 2);
    }

    /**
     * Convert bytes to human readable format
     */
    public static function fileSize(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Convert a number to human readable format
     */
    public static function forHumans(float $number, int $precision = 1): string
    {
        if ($number < 1000) {
            return (string) $number;
        }

        $units = ['', 'K', 'M', 'B', 'T'];
        $unitIndex = 0;

        while ($number >= 1000 && $unitIndex < count($units) - 1) {
            $number /= 1000;
            $unitIndex++;
        }

        return round($number, $precision) . $units[$unitIndex];
    }

    /**
     * Clamp a number between two values
     */
    public static function clamp(float $number, float $min, float $max): float
    {
        return max($min, min($max, $number));
    }

    /**
     * Convert a number to ordinal format
     */
    public static function ordinal(int $number): string
    {
        $ends = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];

        if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
            return $number . 'th';
        }

        return $number . $ends[$number % 10];
    }

    /**
     * Spell out a number
     */
    public static function spell(int $number): string
    {
        if (class_exists('NumberFormatter')) {
            $formatter = new NumberFormatter('en', NumberFormatter::SPELLOUT);
            return $formatter->format($number);
        }

        // Simple fallback for basic numbers
        $ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
        $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

        if ($number < 20) {
            return $ones[$number];
        } elseif ($number < 100) {
            return $tens[intval($number / 10)] . ($number % 10 ? '-' . $ones[$number % 10] : '');
        }

        return (string) $number; // Fallback for larger numbers
    }

    /**
     * Get currency symbol by currency code
     */
    private static function getCurrencySymbol(string $currency): string
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CNY' => '¥',
            'INR' => '₹',
            'KRW' => '₩',
            'RUB' => '₽',
        ];

        return $symbols[$currency] ?? $currency . ' ';
    }
}