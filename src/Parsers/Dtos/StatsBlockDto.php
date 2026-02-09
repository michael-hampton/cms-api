<?php

namespace App\Parsers\Dtos;

use InvalidArgumentException;

final class StatsBlockDto extends BaseBlockDto
{
    private const KNOWN_KEYS = ['title', 'stats', 'layout', 'context'];
    private const MAX_TITLE_LENGTH = 255;
    private const ALLOWED_LAYOUTS = ['grid', 'row', 'column'];

    public function __construct(
        public string $title,
        public array  $stats,
        public string $layout,
        public string $context
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'title' => '',
            'stats' => [],
            'layout' => 'grid',
            'context' => 'default'
        ]);

        if (empty($data['stats']) || !is_array($data['stats'])) {
            throw new InvalidArgumentException('Stats are required');
        }

        $title = trim($data['title']);
        if (strlen($title) > self::MAX_TITLE_LENGTH) {
            $title = substr($title, 0, self::MAX_TITLE_LENGTH);
        }

        $layout = self::validateEnum(
            $data['layout'],
            self::ALLOWED_LAYOUTS,
            'grid',
            'layout'
        );

        $stats = self::parseStats($data['stats']);

        if (empty($stats)) {
            throw new InvalidArgumentException('At least one valid stat is required');
        }

        return new self($title, $stats, $layout, $data['context']);
    }

    private static function parseStats(array $stats): array
    {
        $parsed = [];

        foreach ($stats as $stat) {
            if (empty($stat['number']) || empty($stat['label'])) {
                continue;
            }

            $parsed[] = [
                'number' => trim($stat['number']),
                'label' => trim($stat['label']),
                'description' => trim($stat['description'] ?? ''),
                'icon' => $stat['icon'] ?? ''
            ];
        }

        return $parsed;
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'stats' => $this->stats,
            'layout' => $this->layout,
            'context' => $this->context,
            'stat_count' => count($this->stats)
        ];
    }

    public function getType(): string
    {
        return 'stats';
    }
}