<?php

namespace App\Parsers;

// MapLocationBlockParser.php
namespace App\Parsers;

use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\NumericRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\MaxRule;

class MapLocationBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'map-location';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [new MaxLengthRule(255)],
            'address' => [new RequiredRule(), new MaxLengthRule(500)],
            'latitude' => [new NumericRule()],
            'longitude' => [new NumericRule()],
            'zoom' => [new RequiredRule(), new MinRule(1), new MaxRule(20)],
            'mapType' => [new RequiredRule(), new MaxLengthRule(50)],
            'showMarker' => [new BooleanRule()],
            'height' => [new RequiredRule(), new MinRule(100)],
            'description' => [new MaxLengthRule(1000)]
        ];
    }

    public function parse(array $data): array
    {
        return [
            'title' => trim($data['title'] ?? ''),
            'address' => trim($data['address'] ?? ''),
            'latitude' => (float)($data['latitude'] ?? 0),
            'longitude' => (float)($data['longitude'] ?? 0),
            'zoom' => (int)($data['zoom'] ?? 10),
            'mapType' => $data['mapType'] ?? 'roadmap',
            'showMarker' => (bool)($data['showMarker'] ?? true),
            'height' => (int)($data['height'] ?? 400),
            'description' => trim($data['description'] ?? ''),
            'formatted_title' => htmlspecialchars($data['title'] ?? ''),
            'formatted_address' => htmlspecialchars($data['address'] ?? ''),
            'formatted_description' => nl2br(htmlspecialchars($data['description'] ?? ''))
        ];
    }

    public function generateHtml(array $parsedData): string
    {
        $html = "<div class=\"map-location-block\">";

        if (!empty($parsedData['title'])) {
            $html .= "<h3 class=\"map-title\">{$parsedData['formatted_title']}</h3>";
        }

        $html .= "<div class=\"map-container\" style=\"height: {$parsedData['height']}px;\">";
        $html .= "<iframe ";
        $html .= "width=\"100%\" ";
        $html .= "height=\"{$parsedData['height']}\" ";
        $html .= "frameborder=\"0\" ";
        $html .= "style=\"border:0\" ";
        $html .= "referrerpolicy=\"no-referrer-when-downgrade\" ";
        $html .= "src=\"https://www.google.com/maps/embed/v1/place?key=YOUR_API_KEY&q=" . urlencode($parsedData['address']) . "&zoom={$parsedData['zoom']}\" ";
        $html .= "allowfullscreen>";
        $html .= "</iframe>";
        $html .= "</div>";

        if (!empty($parsedData['description'])) {
            $html .= "<div class=\"map-description\">{$parsedData['formatted_description']}</div>";
        }

        $html .= "</div>";

        return $html;
    }
}