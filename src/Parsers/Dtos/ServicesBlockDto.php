<?php

namespace App\Parsers\Dtos;

use InvalidArgumentException;

final class ServicesBlockDto extends BaseBlockDto
{
    private const KNOWN_KEYS = ['title', 'subtitle', 'services', 'layout'];
    private const MAX_TITLE_LENGTH = 255;
    private const MAX_SUBTITLE_LENGTH = 500;
    private const ALLOWED_LAYOUTS = ['grid', 'list', 'carousel'];

    public function __construct(
        public string $title,
        public string $subtitle,
        public array  $services,
        public string $layout
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'title' => 'Our Services',
            'subtitle' => '',
            'services' => [],
            'layout' => 'grid'
        ]);

        if (empty($data['services']) || !is_array($data['services'])) {
            throw new InvalidArgumentException('Services are required');
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

        $services = self::parseServices($data['services']);

        if (empty($services)) {
            throw new InvalidArgumentException('At least one service is required');
        }

        return new self($title, $subtitle, $services, $layout);
    }

    private static function parseServices(array $services): array
    {
        $parsed = [];

        foreach ($services as $service) {
            if (empty($service['title'])) {
                continue;
            }

            $parsed[] = [
                'title' => trim($service['title']),
                'description' => trim($service['description'] ?? ''),
                'icon' => $service['icon'] ?? '🏠',
                'image' => $service['image'] ?? null,
                'url' => $service['url'] ?? '#'
            ];
        }

        return $parsed;
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'services' => $this->services,
            'layout' => $this->layout,
            'service_count' => count($this->services)
        ];
    }

    public function getType(): string
    {
        return 'services';
    }
}