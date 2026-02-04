<?php

namespace App\Parsers;

use App\Enums\Blocks\DisplayType;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\EmailRule;
use App\Framework\Validation\Rules\EnumRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\UrlRule;
use App\Validation\Custom\SocialMediaUrlRule;

class PersonBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'person';
    }

    public function getValidationRules(): array
    {
        return [
            'name' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'role' => [
                new MaxLengthRule(255)
            ],
            'strapline' => [
                new MaxLengthRule(500)
            ],
            'bio' => [
                new MaxLengthRule(2000)
            ],
            'enableSchema' => [
                new BooleanRule()
            ],
            'email' => [
                new EmailRule()
            ],
            'twitter' => [
                new SocialMediaUrlRule()
            ],
            'website' => [
                new UrlRule()
            ],
            'instagram' => [
                new SocialMediaUrlRule()
            ],
            'facebook' => [
                new SocialMediaUrlRule()
            ],
            'linkedin' => [
                new SocialMediaUrlRule()
            ],
            'tiktok' => [
                new SocialMediaUrlRule()
            ],
            'youtube' => [
                new SocialMediaUrlRule()
            ],
            'displayType' => [
                new EnumRule(DisplayType::class)
            ],
            'context' => [
                new MaxLengthRule(20)
            ]
        ];
    }

    public function parse(array $data): array
    {
        $bio = trim($data['bio'] ?? '');
        $strapline = trim($data['strapline'] ?? '');


        return [
            'name' => trim($data['name'] ?? ''),
            'role' => trim($data['role'] ?? ''),
            'strapline' => $strapline,
            'bio' => $bio,
            'image' => $data['image'] ?? null,
            'phone' => $data['phone'] ?? null,
            'enableSchema' => (bool)($data['enableSchema'] ?? false),
            'email' => $data['email'] ?? null,
            'twitter' => $this->formatSocialUrl($data['twitter'] ?? ''),
            'website' => $data['website'] ?? null,
            'instagram' => $this->formatSocialUrl($data['instagram'] ?? ''),
            'facebook' => $this->formatSocialUrl($data['facebook'] ?? ''),
            'linkedin' => $this->formatSocialUrl($data['linkedin'] ?? ''),
            'tiktok' => $this->formatSocialUrl($data['tiktok'] ?? ''),
            'youtube' => $this->formatSocialUrl($data['youtube'] ?? ''),
            'bio_word_count' => str_word_count(strip_tags($bio)),
            'strapline_word_count' => str_word_count($strapline),
            'formatted_bio' => nl2br(htmlspecialchars($bio)),
            'formatted_name' => htmlspecialchars($data['name']),
            'formatted_role' => htmlspecialchars($data['role']),
            'formatted_strapline' => htmlspecialchars($strapline),
            'social_links' => $this->getSocialLinks($data),
            'displayType' => $data['displayType'] ?? 'profile',
            'context' => $data['context'] ?? 'default',
        ];
    }

    private function formatSocialUrl(string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        // If it starts with @, keep as username
        if (strpos($value, '@') === 0) {
            return $value;
        }

        // If it's already a URL, return as is
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        return null;
    }

    private function getSocialLinks(array $data): array
    {
        $links = [];
        $socialFields = ['twitter', 'instagram', 'facebook', 'linkedin', 'tiktok', 'youtube'];

        foreach ($socialFields as $field) {
            $value = $this->formatSocialUrl($data[$field] ?? '');
            if ($value) {
                $links[$field] = $value;
            }
        }

        if (!empty($data['website'])) {
            $links['website'] = $data['website'];
        }

        return $links;
    }

    public function generateHtml(array $parsedData): string
    {
        $displayType = $parsedData['displayType'] ?? 'contact';
        $contextClass = $parsedData['context'] === 'sidebar' ? ' person-sidebar' : '';

        if ($displayType === 'contact') {
            // Contact info layout
            $html = "<div class=\"person-block person-display-contact{$contextClass}\">";
            $html .= "<div class=\"contact-info\">";
            $html .= "<h3>Contact Information</h3>";

            if (!empty($parsedData['phone'])) {
                $html .= "<div class=\"contact-item\">";
                $html .= "<span class=\"contact-icon\">📞</span>";
                $html .= "<div>";
                $html .= "<strong>Phone</strong><br>";
                $html .= "<a href=\"tel:{$parsedData['phone']}\">{$parsedData['phone']}</a>";
                $html .= "</div>";
                $html .= "</div>";
            }

            if (!empty($parsedData['email'])) {
                $html .= "<div class=\"contact-item\">";
                $html .= "<span class=\"contact-icon\">✉️</span>";
                $html .= "<div>";
                $html .= "<strong>Email</strong><br>";
                $html .= "<a href=\"mailto:{$parsedData['email']}\">{$parsedData['email']}</a>";
                $html .= "</div>";
                $html .= "</div>";
            }

            if (!empty($parsedData['address'])) {
                $html .= "<div class=\"contact-item\">";
                $html .= "<span class=\"contact-icon\">📍</span>";
                $html .= "<div>";
                $html .= "<strong>Address</strong><br>";
                $html .= $parsedData['formatted_address'];
                $html .= "</div>";
                $html .= "</div>";
            }

            $html .= "</div>";
            $html .= "</div>";

            return $html;
        }

        // Default to profile layout for any other displayType or if not specified
        $html = "<div class=\"person-block person-display-profile{$contextClass}\">";

        if ($parsedData['image']) {
            $html .= "<div class=\"person-image\">";
            $html .= "<img src=\"{$parsedData['image']['src']}\" alt=\"{$parsedData['formatted_name']}\">";
            $html .= "</div>";
        }

        $html .= "<div class=\"person-info\">";
        $html .= "<h3 class=\"person-name\">{$parsedData['formatted_name']}</h3>";

        if (!empty($parsedData['role'])) {
            $html .= "<div class=\"person-role\">{$parsedData['formatted_role']}</div>";
        }

        if (!empty($parsedData['bio'])) {
            $html .= "<div class=\"person-bio\">{$parsedData['formatted_bio']}</div>";
        }

        if (!empty($parsedData['email']) || !empty($parsedData['phone'])) {
            $html .= "<div class=\"person-contact\">";
            if (!empty($parsedData['email'])) {
                $html .= "<a href=\"mailto:{$parsedData['email']}\" class=\"contact-link\">Email</a>";
            }
            if (!empty($parsedData['phone'])) {
                $html .= "<a href=\"tel:{$parsedData['phone']}\" class=\"contact-link\">Call</a>";
            }
            $html .= "</div>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }
}