<?php

// AwardBlockParser.php
namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\MaxRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\RequiredRule;

class AwardBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'award';
    }

    public function getValidationRules(): array
    {
        return [
            'subcategory' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'productName' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'image' => [
                new ArrayRule()
            ],
            'caption' => [
                new MaxLengthRule(500)
            ],
            'alt' => [
               // new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'winner' => [
                new BooleanRule()
            ],
            'strapline' => [
                new MaxLengthRule(500)
            ],
            'rating' => [
                new MinRule(0),
                new MaxRule(5)
            ]
        ];
    }

    public function parse(array $data): array
    {
        $caption = trim($data['caption'] ?? '');
        $strapline = trim($data['strapline'] ?? '');
        $rating = (float)($data['rating'] ?? 0);

        return [
            'subcategory' => trim($data['subcategory'] ?? ''),
            'productName' => trim($data['productName'] ?? ''),
            'image' => $data['image'] ?? null,
            'caption' => $caption,
            'alt' => trim($data['alt'] ?? ''),
            'winner' => (bool)($data['winner'] ?? false),
            'strapline' => $strapline,
            'rating' => $rating,
            //'rating_stars' => $this->generateRatingStars($rating),
            'caption_word_count' => str_word_count($caption),
            'strapline_word_count' => str_word_count($strapline),
            'formatted_caption' => nl2br(htmlspecialchars($caption)),
            'formatted_strapline' => nl2br(htmlspecialchars($strapline)),
        ];
    }

    public function generateHtml(array $parsedData): string
    {
        $html = "<div class=\"award-block" . ($parsedData['winner'] ? ' award-winner' : '') . "\">";

        if (!empty($parsedData['image'])) {
            $html .= "<div class=\"award-image\">";
            $html .= "<img src=\"{$parsedData['image']['src']}\" alt=\"{$parsedData['alt']}\" class=\"award-img\">";
            $html .= "</div>";
        }

        $html .= "<div class=\"award-content\">";

        $html .= "<div class=\"award-subcategory\">{$parsedData['subcategory']}</div>";
        $html .= "<h3 class=\"award-product-name\">{$parsedData['productName']}</h3>";

        if ($parsedData['winner']) {
            $html .= "<div class=\"award-winner-badge\">Winner</div>";
        }

        if (!empty($parsedData['strapline'])) {
            $html .= "<div class=\"award-strapline\">{$parsedData['formatted_strapline']}</div>";
        }

        if (!empty($parsedData['caption'])) {
            $html .= "<div class=\"award-caption\">{$parsedData['formatted_caption']}</div>";
        }

        if ($parsedData['rating'] > 0) {
            $html .= "<div class=\"award-rating\">Rating: {$parsedData['rating']}/5</div>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }
}
