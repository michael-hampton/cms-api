<?php

namespace App\Parsers\Dtos;

use InvalidArgumentException;

final class ZoneBlockDto extends BaseBlockDto
{
    private const KNOWN_KEYS = [
        'id', 'name', 'columns', 'blocks', 'options', 'sortOrder'
    ];

    private const ALLOWED_BACKGROUNDS = ['default', 'muted', 'brand'];
    private const ALLOWED_PADDINGS = ['small', 'medium', 'large'];
    private const ALLOWED_WIDTHS = ['contained', 'full'];
    private const MIN_COLUMNS = 1;
    private const MAX_COLUMNS = 4;

    public function __construct(
        public string $id,
        public string $name,
        public int    $columns,
        public array  $blocks,
        public array  $options,
        public int    $sortOrder
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'name' => '',
            'columns' => 1,
            'blocks' => [],
            'options' => [],
            'sortOrder' => 0
        ]);

        if (empty($data['id'])) {
            throw new InvalidArgumentException('Zone ID is required');
        }

        $columns = self::validateRange(
            (int)$data['columns'],
            self::MIN_COLUMNS,
            self::MAX_COLUMNS,
            'columns'
        );

        $options = self::parseOptions($data['options']);

        return new self(
            $data['id'],
            trim($data['name']),
            $columns,
            is_array($data['blocks']) ? $data['blocks'] : [],
            $options,
            (int)$data['sortOrder']
        );
    }

    private static function parseOptions(array $options): array
    {
        return [
            'background' => self::validateEnum(
                $options['background'] ?? 'default',
                self::ALLOWED_BACKGROUNDS,
                'default',
                'background'
            ),
            'padding' => self::validateEnum(
                $options['padding'] ?? 'medium',
                self::ALLOWED_PADDINGS,
                'medium',
                'padding'
            ),
            'width' => self::validateEnum(
                $options['width'] ?? 'contained',
                self::ALLOWED_WIDTHS,
                'contained',
                'width'
            )
        ];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'columns' => $this->columns,
            'blocks' => $this->blocks,
            'options' => $this->options,
            'sortOrder' => $this->sortOrder
        ];
    }

    public function getType(): string
    {
        return 'zone';
    }
}