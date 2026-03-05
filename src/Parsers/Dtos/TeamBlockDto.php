<?php

namespace App\Parsers\Dtos;

use InvalidArgumentException;

final class TeamBlockDto extends BaseBlockDto
{
    private const KNOWN_KEYS = ['title', 'members', 'layout'];
    private const MAX_TITLE_LENGTH = 255;
    private const MAX_SUBTITLE_LENGTH = 500;
    private const ALLOWED_LAYOUTS = ['grid', 'list', 'carousel'];

    public function __construct(
        public string $title,
        public string $subtitle,
        public array  $members,
        public string $layout
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'title' => 'Meet Our Team',
            'subtitle' => '',
            'members' => [],
            'layout' => 'grid'
        ]);

        if (empty($data['members']) || !is_array($data['members'])) {
            throw new InvalidArgumentException('Team members are required');
        }

        $title = trim($data['title']);
        if (strlen($title) > self::MAX_TITLE_LENGTH) {
            $title = substr($title, 0, self::MAX_TITLE_LENGTH);
        }

        $subtitle = trim($data['subtitle']);
        if (strlen($subtitle) > self::MAX_SUBTITLE_LENGTH) {
            $subtitle = substr($subtitle, 0, self::MAX_SUBTITLE_LENGTH);
        }

        $layout = self::validateEnum(
            $data['layout'],
            self::ALLOWED_LAYOUTS,
            'grid',
            'layout'
        );

        $members = self::parseMembers($data['members']);

        if (empty($members)) {
            throw new InvalidArgumentException('At least one team member is required');
        }

        return new self($title, $subtitle, $members, $layout);
    }

    private static function parseMembers(array $members): array
    {
        $parsed = [];

        foreach ($members as $member) {
            if (empty($member['name'])) {
                continue;
            }

            $parsed[] = [
                'name' => trim($member['name']),
                'role' => trim($member['role'] ?? ''),
                'bio' => trim($member['bio'] ?? ''),
                'image' => $member['image'] ?? null,
                'email' => $member['email'] ?? '',
                'phone' => $member['phone'] ?? '',
                'specialties' => array_filter($member['specialties'] ?? [])
            ];
        }

        return $parsed;
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'members' => $this->members,
            'layout' => $this->layout,
            'member_count' => count($this->members)
        ];
    }

    public function getType(): string
    {
        return 'team';
    }
}