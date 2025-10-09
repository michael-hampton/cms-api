<?php

namespace App\Parsers;

use App\Enums\Alignment;
use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\EnumRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\UrlRule;

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
            ],
            'alignment' => [
                new EnumRule(Alignment::class)
            ],
            'linkUrl' => [
                new UrlRule()
            ],
            'linkText' => [
                new MaxLengthRule(100)
            ],
            'noFollow' => [
                new BooleanRule()
            ],
            'sponsored' => [
                new BooleanRule()
            ],
            'openInNewTab' => [
                new BooleanRule()
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
            'has_image' => !empty($data['image']),
            'alignment' => $data['alignment'] ?? 'fullscreen',
            'linkUrl' => $data['linkUrl'] ?? '',
            'linkText' => $data['linkText'] ?? 'Learn More',
            'noFollow' => (bool)($data['noFollow'] ?? false),
            'sponsored' => (bool)($data['sponsored'] ?? false),
            'openInNewTab' => (bool)($data['openInNewTab'] ?? false),
            'has_link' => !empty($data['linkUrl']),
            'link_attributes' => $this->buildLinkAttributes(
                $data['noFollow'] ?? false,
                $data['sponsored'] ?? false,
                $data['openInNewTab'] ?? false
            )
        ];
    }

    private function buildLinkAttributes(bool $noFollow, bool $sponsored, bool $openInNewTab): array
    {
        $attributes = [];

        if ($openInNewTab) {
            $attributes['target'] = '_blank';
            $attributes['rel'] = 'noopener noreferrer';
        }

        $relValues = [];
        if ($noFollow) $relValues[] = 'nofollow';
        if ($sponsored) $relValues[] = 'sponsored';

        if (!empty($relValues)) {
            if (isset($attributes['rel'])) {
                $attributes['rel'] .= ' ' . implode(' ', $relValues);
            } else {
                $attributes['rel'] = implode(' ', $relValues);
            }
        }

        return $attributes;
    }

    public function generateHtml(array $parsedData): string
    {
        $alignmentClass = 'note-align-' . $parsedData['alignment'];
        $html = "<div class=\"note-block {$alignmentClass}\">";

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

        if ($parsedData['has_link']) {
            $linkAttrs = '';
            foreach ($parsedData['link_attributes'] as $attr => $value) {
                $linkAttrs .= " {$attr}=\"{$value}\"";
            }
            $html .= "<a href=\"{$parsedData['linkUrl']}\"{$linkAttrs} class=\"note-link\">";
            $html .= $parsedData['linkText'];
            $html .= "</a>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }
}