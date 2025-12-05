<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;

class TeamBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'team';
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
            'members' => [
                new RequiredRule(),
                new ArrayRule()
            ]
        ];
    }

    public function parse(array $data): array
    {
        $members = [];

        foreach ($data['members'] ?? [] as $member) {
            if (!empty($member['name'])) {
                $members[] = [
                    'name' => trim($member['name']),
                    'role' => trim($member['role'] ?? ''),
                    'bio' => trim($member['bio'] ?? ''),
                    'image' => $member['image'] ?? null,
                    'email' => $member['email'] ?? '',
                    'phone' => $member['phone'] ?? '',
                    'specialties' => array_filter($member['specialties'] ?? []),
                    'formatted_name' => htmlspecialchars($member['name']),
                    'formatted_role' => htmlspecialchars($member['role'] ?? ''),
                    'formatted_bio' => nl2br(htmlspecialchars($member['bio'] ?? ''))
                ];
            }
        }

        return [
            'title' => trim($data['title'] ?? 'Meet Our Team'),
            'subtitle' => trim($data['subtitle'] ?? ''),
            'members' => $members,
            'member_count' => count($members),
            'layout' => $data['layout'] ?? 'grid',
            'formatted_title' => htmlspecialchars($data['title'] ?? 'Meet Our Team'),
            'formatted_subtitle' => htmlspecialchars($data['subtitle'] ?? '')
        ];
    }

    public function generateHtml(array $parsedData): string
    {
        $memberCount = count($parsedData['members']);
        $showCarousel = $memberCount > 3; // Show carousel if more than 3 members

        $html = "<section class=\"team-block team-layout-{$parsedData['layout']}\">";
        $html .= "<div class=\"container\">";

        $html .= "<div class=\"team-header\">";
        $html .= "<h2>{$parsedData['formatted_title']}</h2>";
        if (!empty($parsedData['subtitle'])) {
            $html .= "<p>{$parsedData['formatted_subtitle']}</p>";
        }
        $html .= "</div>";

        if ($showCarousel) {
            $html .= "<div class=\"team-carousel-wrapper\">";

            // Navigation buttons
            $html .= "<button class=\"team-nav team-nav-prev\" onclick=\"scrollTeamCarousel(this, 'prev')\" aria-label=\"Previous\">";
            $html .= "<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"15 18 9 12 15 6\"></polyline></svg>";
            $html .= "</button>";

            $html .= "<button class=\"team-nav team-nav-next\" onclick=\"scrollTeamCarousel(this, 'next')\" aria-label=\"Next\">";
            $html .= "<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"9 18 15 12 9 6\"></polyline></svg>";
            $html .= "</button>";

            $html .= "<div class=\"team-carousel\" data-team-carousel>";
        } else {
            $html .= "<div class=\"team-grid\">";
        }

        foreach ($parsedData['members'] as $member) {
            $personParser = new PersonBlockParser();
            $memberData = [
                'name' => $member['name'],
                'role' => $member['role'],
                'bio' => $member['bio'],
                'email' => $member['email'],
                'phone' => $member['phone'],
                'image' => $member['image'],
                'displayType' => 'profile'
            ];
            $parsedMember = $personParser->parse($memberData);
            $html .= $personParser->generateHtml($parsedMember);
        }

        $html .= "</div>"; // Close team-grid or team-carousel

        // Add indicators for carousel
        if ($showCarousel && $memberCount > 1) {
            $html .= "<div class=\"team-indicators\">";
            for ($i = 0; $i < $memberCount; $i++) {
                $activeClass = $i === 0 ? ' active' : '';
                $html .= "<button class=\"team-indicator{$activeClass}\" onclick=\"scrollTeamToIndex(this, {$i})\" aria-label=\"Go to member " . ($i + 1) . "\"></button>";
            }
            $html .= "</div>";
        }

        if ($showCarousel) {
            $html .= "</div>"; // Close team-carousel-wrapper
        }

        $html .= "</div>"; // Close container
        $html .= "</section>";

        return $html;
    }
}