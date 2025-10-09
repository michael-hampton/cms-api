<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\UrlRule;

class BuyingGuideBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'buying-guide';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'subtitle' => [
                new MaxLengthRule(500)
            ],
            'url' => [
                new UrlRule()
            ],
            'specs' => [
                new ArrayRule()
            ],
            'pros' => [
                new ArrayRule()
            ],
            'cons' => [
                new ArrayRule()
            ],
            'showReviewPanel' => [
                new BooleanRule()
            ],
            'image' => [
                new ArrayRule()
            ]
        ];
    }

    public function parse(array $data): array
    {
        $specs = [];
        foreach ($data['specs'] ?? [] as $spec) {
            if (!empty($spec['text']) && !empty($spec['value'])) {
                $specs[] = [
                    'text' => trim($spec['text']),
                    'value' => trim($spec['value'])
                ];
            }
        }

        return [
            'title' => trim($data['title'] ?? ''),
            'subtitle' => trim($data['subtitle'] ?? ''),
            'url' => $data['url'] ?? '',
            'linkText' => $data['linkText'] ?? 'Learn More',
            'specs' => $specs,
            'pros' => array_filter($data['pros'] ?? []),
            'cons' => array_filter($data['cons'] ?? []),
            'showReviewPanel' => (bool)($data['showReviewPanel'] ?? false),
            'displayAs' => $data['displayAs'] ?? 'button',
            'noFollow' => (bool)($data['noFollow'] ?? false),
            'sponsored' => (bool)($data['sponsored'] ?? false),
            'openInNewTab' => (bool)($data['openInNewTab'] ?? false),
            'has_specs' => !empty($specs),
            'has_pros_cons' => !empty($data['pros']) || !empty($data['cons']),
            'image' => $data['image'] ?? null,
            'has_image' => !empty($data['image'])
        ];
    }

    public function generateHtml(array $parsedData): string
    {
        $html = "<div class=\"buying-guide-block\">";

        if ($parsedData['has_image']) {
            $html .= "<div class=\"buying-guide-image\">";
            $html .= "<img src=\"{$parsedData['image']}\" alt=\"{$parsedData['title']}\" class=\"guide-img\">";
            $html .= "</div>";
        }

        $html .= "<div class=\"buying-guide-header\">";
        $html .= "<h3 class=\"buying-guide-title\">{$parsedData['title']}</h3>";

        if (!empty($parsedData['subtitle'])) {
            $html .= "<p class=\"buying-guide-subtitle\">{$parsedData['subtitle']}</p>";
        }
        $html .= "</div>";

        if ($parsedData['has_specs']) {
            $html .= "<div class=\"buying-guide-specs\">";
            $html .= "<h4>Specifications</h4>";
            $html .= "<dl class=\"specs-list\">";
            foreach ($parsedData['specs'] as $spec) {
                $html .= "<dt>" . htmlspecialchars($spec['text']) . "</dt>";
                $html .= "<dd>" . htmlspecialchars($spec['value']) . "</dd>";
            }
            $html .= "</dl>";
            $html .= "</div>";
        }

        if ($parsedData['showReviewPanel'] && $parsedData['has_pros_cons']) {
            $html .= "<div class=\"buying-guide-review\">";

            if (!empty($parsedData['pros'])) {
                $html .= "<div class=\"guide-pros\">";
                $html .= "<h5>Advantages</h5>";
                $html .= "<ul>";
                foreach ($parsedData['pros'] as $pro) {
                    $html .= "<li>" . htmlspecialchars($pro) . "</li>";
                }
                $html .= "</ul>";
                $html .= "</div>";
            }

            if (!empty($parsedData['cons'])) {
                $html .= "<div class=\"guide-cons\">";
                $html .= "<h5>Considerations</h5>";
                $html .= "<ul>";
                foreach ($parsedData['cons'] as $con) {
                    $html .= "<li>" . htmlspecialchars($con) . "</li>";
                }
                $html .= "</ul>";
                $html .= "</div>";
            }

            $html .= "</div>";
        }

        if (!empty($parsedData['url'])) {
            $linkAttrs = '';
            if ($parsedData['noFollow']) $linkAttrs .= ' rel="nofollow"';
            if ($parsedData['sponsored']) $linkAttrs .= ' rel="sponsored"';
            if ($parsedData['openInNewTab']) $linkAttrs .= ' target="_blank"';

            $html .= "<a href=\"{$parsedData['url']}\"{$linkAttrs} class=\"buying-guide-button\">";
            $html .= $parsedData['linkText'];
            $html .= "</a>";
        }

        $html .= "</div>";

        return $html;
    }
}