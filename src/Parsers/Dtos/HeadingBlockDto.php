<?php

namespace App\Parsers\Dtos;

use App\Enums\Blocks\HeadingLevel;
use InvalidArgumentException;

final class HeadingBlockDto extends BaseBlockDto
{
    private const KNOWN_KEYS = ['text', 'subtitle', 'level', 'context'];
    private const MAX_TEXT_LENGTH = 255;
    private const MAX_SUBTITLE_LENGTH = 500;

    public function __construct(
        public string $text,
        public string $subtitle,
        public string $level,
        public string $context
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'text' => '',
            'subtitle' => '',
            'level' => 'h2',
            'context' => 'default'
        ]);

        // Normalize level input
        if (is_int($data['level'])) {
            $data['level'] = 'h' . $data['level'];
        }

        $text = trim($data['text']);
        if (empty($text)) {
            throw new InvalidArgumentException('Heading text is required');
        }

        if (strlen($text) > self::MAX_TEXT_LENGTH) {
            $text = substr($text, 0, self::MAX_TEXT_LENGTH);
        }

        $subtitle = trim($data['subtitle']);
        if (strlen($subtitle) > self::MAX_SUBTITLE_LENGTH) {
            $subtitle = substr($subtitle, 0, self::MAX_SUBTITLE_LENGTH);
        }

        // Validate level is valid HeadingLevel enum
        try {
            $levelEnum = HeadingLevel::from($data['level']);
            $level = $levelEnum->getLevel();
        } catch (\ValueError $e) {
            if (self::$debugMode) {
                error_log("WARNING: Invalid heading level '{$data['level']}', using h2");
            }
            $level = 'h2';
        }

        return new self($text, $subtitle, $level, $data['context']);
    }

    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'subtitle' => $this->subtitle,
            'level' => $this->level,
            'context' => $this->context,
            'has_subtitle' => !empty($this->subtitle),
            'word_count' => str_word_count($this->text . ' ' . $this->subtitle)
        ];
    }

    public function getType(): string
    {
        return 'heading';
    }
}