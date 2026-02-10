<?php

namespace App\Parsers\Dtos;

use InvalidArgumentException;

final class SectionBlockDto extends BaseBlockDto
{
    private const KNOWN_KEYS = [
        'title', 'headingType', 'navigationText', 'excludeFromNav'
    ];

    private const ALLOWED_HEADING_TYPES = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
    private const MAX_TITLE_LENGTH = 255;

    public function __construct(
        public string $title,
        public string $headingType,
        public string $navigationText,
        public bool   $excludeFromNav,
        public string $context
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'title' => '',
            'headingType' => 'h2',
            'navigationText' => '',
            'excludeFromNav' => false,
            'context' => 'default'
        ]);

        $title = trim($data['title']);
        if (empty($title)) {
            throw new InvalidArgumentException('Section title is required');
        }

        if (strlen($title) > self::MAX_TITLE_LENGTH) {
            $title = substr($title, 0, self::MAX_TITLE_LENGTH);
        }

        $headingType = self::validateEnum(
            $data['headingType'],
            self::ALLOWED_HEADING_TYPES,
            'h2',
            'headingType'
        );

        $navigationText = trim($data['navigationText']);
        if (strlen($navigationText) > self::MAX_TITLE_LENGTH) {
            $navigationText = substr($navigationText, 0, self::MAX_TITLE_LENGTH);
        }

        return new self(
            $title,
            $headingType,
            $navigationText,
            (bool)$data['excludeFromNav'],
            $data['context']
        );
    }

    public function getHeadingLevel(): int
    {
        return (int)str_replace('h', '', $this->headingType);
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'headingType' => $this->headingType,
            'navigationText' => $this->navigationText,
            'excludeFromNav' => $this->excludeFromNav,
            'context' => $this->context,
            'heading_level' => $this->getHeadingLevel()
        ];
    }

    public function getType(): string
    {
        return 'section';
    }
}