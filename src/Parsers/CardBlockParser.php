<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\MaxLengthRule;

class CardBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'card';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [
                new MaxLengthRule(100)
            ],
            'description' => [
                new MaxLengthRule(1000)
            ],
            'linkUrl' => [
                new MaxLengthRule(500)
            ],
            'buttonType' => [
                new MaxLengthRule(20)
            ],
            'buttonText' => [
                new MaxLengthRule(30)
            ],
            'sponsorDeclaration.sponsoredText' => [
                new MaxLengthRule(50)
            ],
            'sponsorDeclaration.sponsorName' => [
                new MaxLengthRule(100)
            ]
        ];
    }

    public function parse(array $data): array
    {
        return [
            'title' => $this->sanitize($data['title'] ?? ''),
            'image' => $this->parseImage($data['image'] ?? null),
            'endorsement' => $this->parseImage($data['endorsement'] ?? null),
            'description' => $this->sanitize($data['description'] ?? ''),
            'linkUrl' => $this->sanitizeUrl($data['linkUrl'] ?? ''),
            'buttonType' => $this->parseButtonType($data['buttonType'] ?? 'primary'),
            'buttonText' => $this->sanitize($data['buttonText'] ?? 'Learn More'),
            'noFollow' => (bool)($data['noFollow'] ?? false),
            'sponsored' => (bool)($data['sponsored'] ?? false),
            'openInNewTab' => (bool)($data['openInNewTab'] ?? false),
            'sponsorDeclaration' => $this->parseSponsorDeclaration($data['sponsorDeclaration'] ?? null),
            'context' => $this->sanitize($data['context'] ?? 'default'),
            'layout' => $this->sanitize($data['layout'] ?? 'full'),
            'alignment' => $this->sanitize($data['alignment'] ?? 'center'),
            'itemsPerRow' => $this->parseItemsPerRow($data['itemsPerRow'] ?? null)
        ];
    }

    private function parseItemsPerRow($value): int
    {
        $value = (int)$value;
        // Validate range 1-4
        if ($value < 1 || $value > 4) {
            return 3; // Default
        }
        return $value;
    }

    private function parseButtonType(string $value): string
    {
        $allowedTypes = ['primary', 'secondary', 'text'];
        $value = strtolower(trim($value));

        return in_array($value, $allowedTypes, true) ? $value : 'primary';
    }

    private function sanitize(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    private function parseImage(?array $image): ?array
    {
        if (empty($image) || empty($image['src'])) {
            return null;
        }

        return [
            'id' => $image['id'] ?? '',
            'src' => $this->sanitize($image['src']),
            'name' => $this->sanitize($image['name'] ?? ''),
            'alt' => $this->sanitize($image['alt'] ?? ''),
            'caption' => $this->sanitize($image['caption'] ?? '')
        ];
    }

    private function sanitizeUrl(string $url): string
    {
        $url = trim($url);

        // Allow relative URLs, anchors, and full URLs
        if (empty($url)) {
            return '';
        }

        // If it starts with #, /, or http, it's valid
        if (preg_match('/^(#|\/|https?:\/\/)/', $url)) {
            return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        }

        return '';
    }

    private function parseSponsorDeclaration(?array $declaration): ?array
    {
        if (empty($declaration)) {
            return null;
        }

        $hasContent = !empty($declaration['sponsoredText']) ||
            !empty($declaration['sponsorName']) ||
            !empty($declaration['sponsorLogo']);

        if (!$hasContent) {
            return null;
        }

        return [
            'sponsoredText' => $this->sanitize($declaration['sponsoredText'] ?? ''),
            'sponsorName' => $this->sanitize($declaration['sponsorName'] ?? ''),
            'sponsorLogo' => $this->parseImage($declaration['sponsorLogo'] ?? null)
        ];
    }

    public function generateHtml(array $parsedData): string
    {
        $itemsPerRow = $parsedData['itemsPerRow'] ?? 3;
        $layout = $parsedData['layout'] ?? 'full';
        $alignment = $parsedData['alignment'] ?? 'center';
        $containerClass = "card-block card-block-{$parsedData['context']} card-items-{$itemsPerRow} card-layout-{$layout} card-align-{$alignment}";

        $html = "<div class=\"{$containerClass}\">";
        $html .= "<div class=\"card-container\">";

        // Image Section
        if (!empty($parsedData['image'])) {
            $html .= $this->generateImageSection($parsedData);
        }

        // Content Section
        $html .= "<div class=\"card-content\">";

        // Sponsor Declaration (Top)
        if (!empty($parsedData['sponsorDeclaration'])) {
            $html .= $this->generateSponsorDeclaration($parsedData['sponsorDeclaration']);
        }

        // Title
        if (!empty($parsedData['title'])) {
            $html .= "<h3 class=\"card-title\">{$parsedData['title']}</h3>";
        }

        // Description
        if (!empty($parsedData['description'])) {
            $html .= "<div class=\"card-description\">{$parsedData['description']}</div>";
        }

        // Button/Link
        if (!empty($parsedData['linkUrl'])) {
            $html .= $this->generateButton($parsedData);
        }

        $html .= "</div>"; // card-content
        $html .= "</div>"; // card-container
        $html .= "</div>"; // card-block

        return $html;
    }

    private function generateImageSection(array $parsedData): string
    {
        $html = "<div class=\"card-image-wrapper\">";

        if (!empty($parsedData['linkUrl'])) {
            $html .= "<a href=\"{$parsedData['linkUrl']}\"" . $this->getLinkAttributes($parsedData) . ">";
        }

        $html .= "<div class=\"card-image\">";
        $html .= "<img src=\"{$parsedData['image']['src']}\" ";
        $html .= "alt=\"" . ($parsedData['image']['alt'] ?: $parsedData['title']) . "\" ";
        $html .= "loading=\"lazy\">";

        // Endorsement Badge
        if (!empty($parsedData['endorsement'])) {
            $html .= "<div class=\"card-endorsement\">";
            $html .= "<img src=\"{$parsedData['endorsement']['src']}\" ";
            $html .= "alt=\"{$parsedData['endorsement']['alt']}\" ";
            $html .= "loading=\"lazy\">";
            $html .= "</div>";
        }

        $html .= "</div>"; // card-image

        if (!empty($parsedData['linkUrl'])) {
            $html .= "</a>";
        }

        $html .= "</div>"; // card-image-wrapper

        return $html;
    }

    private function getLinkAttributes(array $parsedData): string
    {
        $attributes = [];

        if ($parsedData['openInNewTab']) {
            $attributes[] = 'target="_blank"';
            $attributes[] = 'rel="noopener noreferrer"';
        }

        $rel = [];
        if ($parsedData['noFollow']) {
            $rel[] = 'nofollow';
        }
        if ($parsedData['sponsored']) {
            $rel[] = 'sponsored';
        }

        if (!empty($rel)) {
            $relString = implode(' ', $rel);
            if ($parsedData['openInNewTab']) {
                // Merge with existing noopener noreferrer
                $existingRel = array_filter($attributes, fn($attr) => str_starts_with($attr, 'rel='));
                if ($existingRel) {
                    // Remove the old rel attribute
                    $attributes = array_filter($attributes, fn($attr) => !str_starts_with($attr, 'rel='));
                }
                $attributes[] = 'rel="noopener noreferrer ' . $relString . '"';
            } else {
                $attributes[] = 'rel="' . $relString . '"';
            }
        }

        return !empty($attributes) ? ' ' . implode(' ', array_unique($attributes)) : '';
    }

    private function generateSponsorDeclaration(array $declaration): string
    {
        $html = "<div class=\"card-sponsor-declaration\">";

        if (!empty($declaration['sponsorLogo'])) {
            $html .= "<div class=\"sponsor-logo\">";
            $html .= "<img src=\"{$declaration['sponsorLogo']['src']}\" ";
            $html .= "alt=\"{$declaration['sponsorLogo']['alt']}\" ";
            $html .= "loading=\"lazy\">";
            $html .= "</div>";
        }

        $html .= "<div class=\"sponsor-text\">";

        if (!empty($declaration['sponsoredText'])) {
            $html .= "<span class=\"sponsored-label\">{$declaration['sponsoredText']}</span>";
        }

        if (!empty($declaration['sponsorName'])) {
            $html .= "<span class=\"sponsor-name\">{$declaration['sponsorName']}</span>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    private function generateButton(array $parsedData): string
    {
        $buttonType = $parsedData['buttonType'] ?? 'primary';
        $buttonText = $parsedData['buttonText'] ?? 'Learn More';
        $url = $parsedData['linkUrl'];

        $class = "card-button card-button-{$buttonType}";
        $attributes = $this->getLinkAttributes($parsedData);

        return "<a href=\"{$url}\" class=\"{$class}\"{$attributes}>{$buttonText}</a>";
    }
}