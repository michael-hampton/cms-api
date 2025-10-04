<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;

class StatsBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'stats';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [
                new MaxLengthRule(255)
            ],
            'stats' => [
                new RequiredRule(),
                new ArrayRule()
            ]
        ];
    }

    public function parse(array $data): array
    {
        $stats = [];

        foreach ($data['stats'] ?? [] as $stat) {
            if (!empty($stat['number']) && !empty($stat['label'])) {
                $stats[] = [
                    'number' => trim($stat['number']),
                    'label' => trim($stat['label']),
                    'description' => trim($stat['description'] ?? ''),
                    'icon' => $stat['icon'] ?? '',
                    'formatted_number' => htmlspecialchars($stat['number']),
                    'formatted_label' => htmlspecialchars($stat['label']),
                    'formatted_description' => htmlspecialchars($stat['description'] ?? '')
                ];
            }
        }

        return [
            'title' => trim($data['title'] ?? ''),
            'stats' => $stats,
            'stat_count' => count($stats),
            'layout' => $data['layout'] ?? 'grid',
            'formatted_title' => htmlspecialchars($data['title'] ?? '')
        ];
    }

    public function generateHtml(array $parsedData): string
    {
        $html = "<section class=\"stats-block stats-layout-{$parsedData['layout']}\">";

        if (!empty($parsedData['title'])) {
            $html .= "<h2 class=\"stats-title\">{$parsedData['formatted_title']}</h2>";
        }

        $html .= "<div class=\"stats-grid\">";

        foreach ($parsedData['stats'] as $stat) {
            $html .= "<div class=\"stat-item\">";

            if (!empty($stat['icon'])) {
                $html .= "<div class=\"stat-icon\">{$stat['icon']}</div>";
            }

            $html .= "<div class=\"stat-number\">{$stat['formatted_number']}</div>";
            $html .= "<div class=\"stat-label\">{$stat['formatted_label']}</div>";

            if (!empty($stat['description'])) {
                $html .= "<div class=\"stat-description\">{$stat['formatted_description']}</div>";
            }

            $html .= "</div>";
        }

        $html .= "</div>";
        $html .= "</section>";

        return $html;
    }
}