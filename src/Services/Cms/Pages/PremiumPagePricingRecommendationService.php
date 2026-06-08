<?php

namespace App\Services\Cms\Pages;

use App\Models\Page;

class PremiumPagePricingRecommendationService
{
    /**
     * Returns price in minor units, so GBP pence.
     */
    public function recommend(Page $page): PremiumPagePricingRecommendation
    {
        $score = 0;
        $reasons = [];

        $wordCount = $this->estimateWordCount($page);

        if ($wordCount >= 2500) {
            $score += 3;
            $reasons[] = 'Long-form article';
        } elseif ($wordCount >= 1200) {
            $score += 2;
            $reasons[] = 'Medium-depth article';
        } elseif ($wordCount >= 600) {
            $score += 1;
            $reasons[] = 'Short premium article';
        }

        if (!empty($page->contributor_id)) {
            $score += 1;
            $reasons[] = 'Contributor-owned content';
        }

        if (!empty($page->published_at)) {
            $reasons[] = 'Already published or publication-ready';
        }

        /**
         * Keep bands deliberately boring.
         * This is a recommendation, not magic AI pricing nonsense.
         */
        $recommendedPrice = match (true) {
            $score >= 4 => 499,
            $score >= 3 => 299,
            $score >= 2 => 199,
            default => 99,
        };

        return new PremiumPagePricingRecommendation(
            recommendedPrice: $recommendedPrice,
            minimumPrice: 99,
            maximumPrice: 999,
            score: $score,
            reasons: $reasons,
            wordCount: $wordCount,
        );
    }

    private function estimateWordCount(Page $page): int
    {
        $text = '';

        if (!empty($page->title)) {
            $text .= ' ' . $page->title;
        }

        if (!empty($page->content)) {
            $text .= ' ' . strip_tags((string) $page->content);
        }

        if (!empty($page->blocks)) {
            $blocks = is_string($page->blocks)
                ? json_decode($page->blocks, true)
                : $page->blocks;

            if (is_array($blocks)) {
                $text .= ' ' . $this->extractTextFromBlocks($blocks);
            }
        }

        $text = trim(preg_replace('/\s+/', ' ', $text));

        if ($text === '') {
            return 0;
        }

        return str_word_count($text);
    }

    private function extractTextFromBlocks(array $blocks): string
    {
        $text = '';

        foreach ($blocks as $block) {
            if (is_array($block)) {
                foreach ($block as $key => $value) {
                    if (is_string($value) && in_array($key, ['content', 'text', 'body', 'heading', 'title'], true)) {
                        $text .= ' ' . strip_tags($value);
                    }

                    if (is_array($value)) {
                        $text .= ' ' . $this->extractTextFromBlocks($value);
                    }
                }
            }
        }

        return $text;
    }
}