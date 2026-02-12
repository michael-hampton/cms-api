<?php

namespace App\Parsers\Dtos;

final class GroupBlockDto extends BaseBlockDto
{
    private const KNOWN_KEYS = ['blocks'];

    public function __construct(
        public string $layout,
        public array  $blocks,
        public string $carouselTitle = ''
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'heading' => '',
            'blocks' => []
        ]);

        return new self(
            $data['layout'],
            $data['blocks'],
            trim($data['carouselTitle'] ?? ''),
        );
    }

    public function toArray(): array
    {
        return [
            'layout' => $this->layout,
            'blocks' => $this->blocks,
            'has_heading' => !empty($this->carouselTitle),
            'children_count' => count($this->blocks),
            'carouselTitle' => $this->carouselTitle,
        ];
    }

    public function getType(): string
    {
        return 'group';
    }
}