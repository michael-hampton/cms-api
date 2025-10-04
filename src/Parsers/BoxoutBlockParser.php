<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;

class BoxoutBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'note';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'paragraphs' => [
                new RequiredRule(),
                new ArrayRule()
            ],
            'image' => [
                new ArrayRule()
            ]
        ];
    }

    public function parse(array $data): array
    {
        $title = trim($data['title'] ?? '');
        $paragraphs = array_filter(array_map('trim', $data['paragraphs'] ?? []), 'strlen');

        return [
            'title' => $title,
            'paragraphs' => $paragraphs,
            'image' => $data['image'] ?? null,
            'formatted_title' => htmlspecialchars($title),
            'formatted_paragraphs' => array_map(function($p) {
                return nl2br(htmlspecialchars($p));
            }, $paragraphs),
            'word_count' => str_word_count($title . ' ' . implode(' ', $paragraphs)),
            'has_image' => !empty($data['image'])
        ];
    }

    public function generateHtml(array $parsedData): string
    {
        $html = "<div class=\"note-block\">";

        if ($parsedData['has_image']) {
            $html .= "<div class=\"note-image\">";
            $html .= "<img src=\"{$parsedData['image']}\" alt=\"{$parsedData['formatted_title']}\" class=\"note-img\">";
            $html .= "</div>";
        }

        $html .= "<div class=\"note-content\">";
        $html .= "<h4 class=\"note-title\">{$parsedData['formatted_title']}</h4>";

        foreach ($parsedData['formatted_paragraphs'] as $paragraph) {
            $html .= "<p class=\"note-paragraph\">{$paragraph}</p>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }
}