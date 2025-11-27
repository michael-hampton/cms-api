<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\RequiredRule;

class TextBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'text';
    }

    public function getValidationRules(): array
    {
        return [
            'paragraphs' => [
                new RequiredRule(),
                new ArrayRule(),
                new MinRule(1) // At least one paragraph required
            ],
            'paragraphs.*' => [
                new RequiredRule(),
                new MaxLengthRule(10000) // Max length per paragraph
            ]
        ];
    }

    public function parse(array $data): array
    {
        $paragraphs = $data['paragraphs'] ?? [];

        // Clean and sanitize paragraphs
        $cleanParagraphs = array_map(function($paragraph) {
            return trim($paragraph);
        }, $paragraphs);

        // Remove empty paragraphs
        $cleanParagraphs = array_filter($cleanParagraphs, function($paragraph) {
            return !empty($paragraph);
        });

        // Reindex array to ensure sequential keys
        $cleanParagraphs = array_values($cleanParagraphs);

        $totalWordCount = $this->calculateTotalWordCount($cleanParagraphs);
        $totalCharCount = $this->calculateTotalCharCount($cleanParagraphs);

        return [
            'context' => $data['context'] ?? 'default',
            'paragraphs' => $cleanParagraphs,
            'paragraph_count' => count($cleanParagraphs),
            'total_word_count' => $totalWordCount,
            'total_char_count' => $totalCharCount,
            'average_words_per_paragraph' => count($cleanParagraphs) > 0 ? round($totalWordCount / count($cleanParagraphs), 2) : 0,
            'formatted_paragraphs' => $this->formatParagraphs($cleanParagraphs),
            'reading_time_minutes' => $this->calculateReadingTime($totalWordCount)
        ];
    }

    private function calculateTotalWordCount(array $paragraphs): int
    {
        $totalWords = 0;
        foreach ($paragraphs as $paragraph) {
            $totalWords += str_word_count($paragraph);
        }
        return $totalWords;
    }

    private function calculateTotalCharCount(array $paragraphs): int
    {
        return array_sum(array_map('strlen', $paragraphs));
    }

    private function formatParagraphs(array $paragraphs): array
    {
        return array_map(function($paragraph) {
            return htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8');
        }, $paragraphs);
    }

    private function calculateReadingTime(int $wordCount): int
    {
        // Average reading speed is ~200 words per minute
        return max(1, round($wordCount / 200));
    }

    public function generateHtml(array $parsedData): string
    {
        $context = $parsedData['context'] ?? 'default';

        if ($context === 'sidebar') {
            return $this->generateSidebarHtml($parsedData);
        }

        return $this->generateDefaultHtml($parsedData);
    }

    private function generateSidebarHtml(array $parsedData): string
    {
        $html = "<div class=\"text-block text-block-sidebar\">";

        foreach ($parsedData['paragraphs'] as $paragraph) {
            $html .= "<p class=\"sidebar-text\">{$paragraph}</p>";
        }

        $html .= "</div>";
        return $html;
    }

    public function generateDefaultHtml(array $parsedData): string
    {
        $html = "<div class=\"text-block\">";

        foreach ($parsedData['formatted_paragraphs'] as $paragraph) {
            $html .= "<p class=\"text-paragraph\">{$paragraph}</p>";
        }

        $html .= "</div>";

        return $html;
    }
}