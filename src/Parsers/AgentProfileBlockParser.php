<?php

namespace App\Parsers;

namespace App\Parsers;

use App\Framework\Validation\Rules\EmailRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\ArrayRule;

class AgentProfileBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'agent-profile';
    }

    public function getValidationRules(): array
    {
        return [
            'name' => [new RequiredRule(), new MaxLengthRule(255)],
            'title' => [new MaxLengthRule(255)],
            'bio' => [new MaxLengthRule(2000)],
            'phone' => [new MaxLengthRule(50)],
            'email' => [new EmailRule()],
            'license' => [new MaxLengthRule(255)],
            'experience' => [new MaxLengthRule(255)],
            'specialties' => [new MaxLengthRule(500)],
            'languages' => [new MaxLengthRule(255)],
            'profileImageUrl' => [new MaxLengthRule(500)],
            'socialMedia' => [new ArrayRule()]
        ];
    }

    public function parse(array $data): array
    {
        return [
            'name' => trim($data['name'] ?? ''),
            'title' => trim($data['title'] ?? ''),
            'bio' => trim($data['bio'] ?? ''),
            'phone' => trim($data['phone'] ?? ''),
            'email' => trim($data['email'] ?? ''),
            'license' => trim($data['license'] ?? ''),
            'experience' => trim($data['experience'] ?? ''),
            'specialties' => trim($data['specialties'] ?? ''),
            'languages' => trim($data['languages'] ?? ''),
            'profileImageUrl' => $data['profileImageUrl'] ?? null,
            'socialMedia' => $data['socialMedia'] ?? [],
            'context' => $data['context'] ?? 'default',
            'formatted_name' => htmlspecialchars($data['name'] ?? ''),
            'formatted_title' => htmlspecialchars($data['title'] ?? ''),
            'formatted_bio' => nl2br(htmlspecialchars($data['bio'] ?? '')),
            'has_image' => !empty($data['profileImageUrl']),
            'has_social' => !empty($data['socialMedia']),
        ];
    }

    public function generateHtml(array $parsedData): string
    {
        $context = $parsedData['context'] ?? 'default'; // Can be 'sidebar', 'main', 'default'

        if ($context === 'sidebar') {
            return $this->generateSidebarHtml($parsedData);
        }

        return $this->generateDefaultHtml($parsedData);
    }

    private function generateSidebarHtml(array $parsedData): string
    {
        $html = "<div class=\"agent-profile-sidebar\">";

        if ($parsedData['has_image']) {
            $html .= "<div class=\"sidebar-agent-image\">";
            $html .= "<img src=\"{$parsedData['profileImageUrl']}\" alt=\"{$parsedData['formatted_name']}\">";
            $html .= "</div>";
        }

        $html .= "<div class=\"sidebar-agent-info\">";
        $html .= "<h4 class=\"sidebar-agent-name\">{$parsedData['formatted_name']}</h4>";

        if (!empty($parsedData['title'])) {
            $html .= "<div class=\"sidebar-agent-title\">{$parsedData['formatted_title']}</div>";
        }

        if (!empty($parsedData['experience'])) {
            $html .= "<div class=\"sidebar-agent-experience\">{$parsedData['experience']} experience</div>";
        }

        $html .= "<div class=\"sidebar-agent-contact\">";
        if (!empty($parsedData['phone'])) {
            $html .= "<a href=\"tel:{$parsedData['phone']}\" class=\"sidebar-contact-btn\">";
            $html .= "<span class=\"contact-icon\">📞</span> Call {$parsedData['phone']}";
            $html .= "</a>";
        }
        if (!empty($parsedData['email'])) {
            $html .= "<a href=\"mailto:{$parsedData['email']}\" class=\"sidebar-contact-btn\">";
            $html .= "<span class=\"contact-icon\">✉️</span> Email Agent";
            $html .= "</a>";
        }
        $html .= "</div>";

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    public function generateDefaultHtml(array $parsedData): string
    {
        $html = "<div class=\"agent-profile-block\">";

        if ($parsedData['has_image']) {
            $html .= "<div class=\"agent-image\">";
            $html .= "<img src=\"{$parsedData['profileImageUrl']}\" alt=\"{$parsedData['formatted_name']}\">";
            $html .= "</div>";
        }

        $html .= "<div class=\"agent-info\">";
        $html .= "<h3 class=\"agent-name\">{$parsedData['formatted_name']}</h3>";

        if (!empty($parsedData['title'])) {
            $html .= "<div class=\"agent-title\">{$parsedData['formatted_title']}</div>";
        }

        if (!empty($parsedData['bio'])) {
            $html .= "<div class=\"agent-bio\">{$parsedData['formatted_bio']}</div>";
        }

        $html .= "<div class=\"agent-contact\">";
        if (!empty($parsedData['phone'])) {
            $html .= "<a href=\"tel:{$parsedData['phone']}\" class=\"contact-link\">{$parsedData['phone']}</a>";
        }
        if (!empty($parsedData['email'])) {
            $html .= "<a href=\"mailto:{$parsedData['email']}\" class=\"contact-link\">{$parsedData['email']}</a>";
        }
        $html .= "</div>";

        if ($parsedData['has_social']) {
            $html .= "<div class=\"agent-social\">";
            foreach ($parsedData['socialMedia'] as $platform => $url) {
                if (!empty($url)) {
                    $html .= "<a href=\"{$url}\" target=\"_blank\" class=\"social-link\">{$platform}</a>";
                }
            }
            $html .= "</div>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }
}