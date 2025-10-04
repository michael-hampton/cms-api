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
        $html = "<section class=\"team-block team-layout-{$parsedData['layout']}\">";
        $html .= "<div class=\"container\">";

        $html .= "<div class=\"team-header\">";
        $html .= "<h2>{$parsedData['formatted_title']}</h2>";
        if (!empty($parsedData['subtitle'])) {
            $html .= "<p>{$parsedData['formatted_subtitle']}</p>";
        }
        $html .= "</div>";

        $html .= "<div class=\"team-grid\">";

        foreach ($parsedData['members'] as $member) {
            // Use PersonBlockParser to generate each member
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

        $html .= "</div>";
        $html .= "</div>";
        $html .= "</section>";

        return $html;
    }
}