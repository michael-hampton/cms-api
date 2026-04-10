<?php

namespace App\Services\OpenCollab;

/**
 * Calculates a readability score for article content.
 *
 * Algorithm: simplified Flesch Reading Ease.
 *   score = 206.835 − (1.015 × ASL) − (84.6 × ASW)
 *   ASL = average sentence length (words per sentence)
 *   ASW = average syllables per word
 *
 * Score range 0–100:
 *   80+  Very easy
 *   65+  Easy
 *   50+  Fairly easy
 *   35+  Difficult
 *   <35  Very difficult
 *
 * This class is a pure calculator — no DB, no events, no side effects.
 * Persistence is handled by ReadabilityService.
 */
class ReadabilityAnalyser
{
    /**
     * Strips HTML, calculates and returns a score 0–100.
     */
    public function analyse(string $rawContent): float
    {
        $text = $this->stripHtml($rawContent);

        if (empty(trim($text))) {
            return 0.0;
        }

        $sentences = $this->countSentences($text);
        $words = $this->words($text);
        $wordCount = count($words);

        if ($wordCount === 0 || $sentences === 0) {
            return 0.0;
        }

        $asl = $wordCount / $sentences;
        $asw = $this->averageSyllablesPerWord($words);
        $score = 206.835 - (1.015 * $asl) - (84.6 * $asw);

        return (float)max(0, min(100, round($score, 1)));
    }

    // -------------------------------------------------------------------------

    private function stripHtml(string $content): string
    {
        // Replace closing tags with spaces before stripping to avoid "TitleText"
        $content = str_replace('>', '> ', $content);
        return html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function countSentences(string $text): int
    {
        $count = preg_match_all('/[.!?]+/', $text);
        return max(1, (int)$count);
    }

    /** @return string[] */
    private function words(string $text): array
    {
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        return array_filter($words, fn($w) => preg_match('/[a-zA-Z]/', $w));
    }

    private function averageSyllablesPerWord(array $words): float
    {
        if (empty($words)) {
            return 0.0;
        }

        $total = array_sum(array_map([$this, 'countSyllables'], $words));
        return $total / count($words);
    }

    /**
     * Heuristic syllable counter — good enough for a readability indicator.
     * Not a dictionary-based approach; trades precision for zero dependencies.
     */
    private function countSyllables(string $word): int
    {
        $word = strtolower(preg_replace('/[^a-zA-Z]/', '', $word));
        if (strlen($word) <= 3) {
            return 1;
        }

        // Remove trailing silent e
        $word = rtrim($word, 'e');

        // Count vowel groups
        $count = preg_match_all('/[aeiouy]+/', $word);

        return max(1, (int)$count);
    }
}