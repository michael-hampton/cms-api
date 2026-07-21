<?php

namespace App\Parsers\Dtos;

use InvalidArgumentException;

final class QuoteBlockDto extends BaseBlockDto
{
    private const KNOWN_KEYS = ['attribution'];
    private const MAX_TEXT_LENGTH = 1000;
    private const MAX_ATTRIBUTION_LENGTH = 255;

    public function __construct(
        public string $text,
        public string $attribution,
        public string $context
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'text' => '',
            'attribution' => '',
            'context' => 'default'
        ]);

        $text = trim($data['text']);
        if (empty($text)) {
            throw new InvalidArgumentException('Quote text is required');
        }

        if (strlen($text) > self::MAX_TEXT_LENGTH) {
            if (self::$debugMode) {
                error_log("WARNING: Quote text exceeds max length, truncating");
            }
            $text = substr($text, 0, self::MAX_TEXT_LENGTH);
        }

        $attribution = trim($data['attribution']);
        if (strlen($attribution) > self::MAX_ATTRIBUTION_LENGTH) {
            $attribution = substr($attribution, 0, self::MAX_ATTRIBUTION_LENGTH);
        }

        return new self($text, $attribution, $data['context']);
    }

    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'attribution' => $this->attribution,
            'context' => $this->context,
            'has_attribution' => !empty($this->attribution),
            'word_count' => str_word_count($this->text)
        ];
    }

    public function getType(): string
    {
        return 'quote';
    }
}