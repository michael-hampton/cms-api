<?php

namespace App\Framework\Support;

class Rating
{
    /**
     * Generate star HTML
     */
    public static function generateStars(int $rating): string
    {
        $clamped = self::clampRating($rating);
        return str_repeat('⭐', $clamped);
    }

    /**
     * Clamp rating to valid range (1-5)
     */
    public static function clampRating(int|float $rating): int
    {
        $rating = (int)$rating;

        if ($rating < 1) {
            return 1;
        }

        if ($rating > 5) {
            return 5;
        }

        return $rating;
    }
}