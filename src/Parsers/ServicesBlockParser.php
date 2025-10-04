<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;

class ServicesBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'services';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [
                new MaxLengthRule(255)
            ],
            'subtitle' => [
                new MaxLengthRule(500)
            ],
            'services' => [
                new RequiredRule(),
                new ArrayRule()
            ]
        ];
    }

    public function parse(array $data): array
    {
        $services = [];

        foreach ($data['services'] ?? [] as $service) {
            if (!empty($service['title'])) {
                $services[] = [
                    'title' => trim($service['title']),
                    'description' => trim($service['description'] ?? ''),
                    'icon' => $service['icon'] ?? '🏠',
                    'image' => $service['image'] ?? null,
                    'url' => $service['url'] ?? '#',
                    'formatted_title' => htmlspecialchars($service['title']),
                    'formatted_description' => nl2br(htmlspecialchars($service['description'] ?? ''))
                ];
            }
        }

        return [
            'title' => trim($data['title'] ?? 'Our Services'),
            'subtitle' => trim($data['subtitle'] ?? ''),
            'services' => $services,
            'service_count' => count($services),
            'layout' => $data['layout'] ?? 'grid',
            'formatted_title' => htmlspecialchars($data['title'] ?? 'Our Services'),
            'formatted_subtitle' => htmlspecialchars($data['subtitle'] ?? '')
        ];
    }

    public function generateHtml(array $parsedData): string
    {
        $html = "<section class=\"services-block services-layout-{$parsedData['layout']}\">";

        $html .= "<div class=\"services-header\">";
        $html .= "<h2>{$parsedData['formatted_title']}</h2>";
        if (!empty($parsedData['subtitle'])) {
            $html .= "<p>{$parsedData['formatted_subtitle']}</p>";
        }
        $html .= "</div>";

        $html .= "<div class=\"services-grid\">";

        foreach ($parsedData['services'] as $service) {
            $html .= "<div class=\"service-card\">";

            if ($service['image']) {
                $html .= "<div class=\"service-image\">";
                $html .= "<img src=\"{$service['image']}\" alt=\"{$service['formatted_title']}\">";
                $html .= "</div>";
            } else {
                $html .= "<div class=\"service-icon\">{$service['icon']}</div>";
            }

            $html .= "<h3 class=\"service-title\">{$service['formatted_title']}</h3>";
            $html .= "<div class=\"service-description\">{$service['formatted_description']}</div>";

            if ($service['url'] !== '#') {
                $html .= "<a href=\"{$service['url']}\" class=\"service-link\">Learn More</a>";
            }

            $html .= "</div>";
        }

        $html .= "</div>";
        $html .= "</section>";

        return $html;
    }
}