<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;

class CtaBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'cta';
    }

    public function getValidationRules(): array
    {
        return [
            'text' => [
                new RequiredRule(),
                new MaxLengthRule(100)
            ],
            'url' => [
                new RequiredRule(),
            ],
            'noFollow' => [
                new BooleanRule()
            ],
            'sponsored' => [
                new BooleanRule()
            ],
            'openInNewTab' => [
                new BooleanRule()
            ],
            'style' => [
                new MaxLengthRule(20)
            ],
            'size' => [
                new MaxLengthRule(20)
            ],
            'alignment' => [
                new MaxLengthRule(20)
            ],
            'context' => [
                new MaxLengthRule(20)
            ]
        ];
    }

    public function parse(array $data): array
    {
        return [
            'text' => trim($data['text'] ?? 'Click Here'),
            'url' => trim($data['url'] ?? ''),
            'noFollow' => (bool)($data['noFollow'] ?? false),
            'sponsored' => (bool)($data['sponsored'] ?? false),
            'openInNewTab' => (bool)($data['openInNewTab'] ?? false),
            'style' => $data['style'] ?? 'primary',
            'size' => $data['size'] ?? 'medium',
            'alignment' => $data['alignment'] ?? 'center',
            'context' => $data['context'] ?? 'default',
            'formatted_text' => htmlspecialchars($data['text'] ?? 'Click Here'),
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
        $styleClass = 'cta-' . $parsedData['style'];
        $sizeClass = 'cta-' . $parsedData['size'];
        $alignmentClass = 'cta-align-' . $parsedData['alignment'];
        $contextClass = $parsedData['context'] === 'sidebar' ? 'cta-sidebar' : '';

        $html = "<div class=\"cta-block {$alignmentClass} {$contextClass}\">";

        $attrs = '';
        foreach ($parsedData['link_attributes'] as $attr => $value) {
            $attrs .= " {$attr}=\"{$value}\"";
        }

        $sponsoredBadge = $parsedData['sponsored'] ? '<span class="sponsored-badge">Sponsored</span>' : '';

        $html .= "<a href=\"{$parsedData['url']}\"{$attrs} class=\"cta-button {$styleClass} {$sizeClass}\">";
        $html .= $parsedData['formatted_text'];
        $html .= $sponsoredBadge;
        $html .= "</a>";

        $html .= "</div>";

        return $html;
    }
}