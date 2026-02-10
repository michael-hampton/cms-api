<?php

namespace App\Parsers\Dtos;

use InvalidArgumentException;

final class TextBlockDto extends BaseBlockDto
{
    private const KNOWN_KEYS = ['paragraphs'];
    private const MAX_PARAGRAPH_LENGTH = 10000;

    public function __construct(
        public array  $paragraphs,
        public string $context
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'paragraphs' => [],
            'context' => 'default'
        ]);

        if (empty($data['paragraphs']) || !is_array($data['paragraphs'])) {
            throw new InvalidArgumentException('At least one paragraph is required');
        }

        $paragraphs = [];
        foreach ($data['paragraphs'] as $paragraph) {
            $cleaned = trim($paragraph);
            if (empty($cleaned)) {
                continue;
            }

            if (strlen($cleaned) > self::MAX_PARAGRAPH_LENGTH) {
                if (self::$debugMode) {
                    error_log("WARNING: Paragraph exceeds max length, truncating");
                }
                $cleaned = substr($cleaned, 0, self::MAX_PARAGRAPH_LENGTH);
            }

            $paragraphs[] = $cleaned;
        }

        if (empty($paragraphs)) {
            throw new InvalidArgumentException('At least one non-empty paragraph is required');
        }

        return new self($paragraphs, $data['context']);
    }

    public function toArray(): array
    {
        return [
            'paragraphs' => $this->paragraphs,
            'context' => $this->context,
            'paragraph_count' => count($this->paragraphs),
            'total_word_count' => $this->getTotalWordCount(),
            'reading_time_minutes' => $this->getReadingTime()
        ];
    }

    private function getTotalWordCount(): int
    {
        $total = 0;
        foreach ($this->paragraphs as $paragraph) {
            $total += str_word_count($paragraph);
        }
        return $total;
    }

    private function getReadingTime(): int
    {
        return max(1, round($this->getTotalWordCount() / 200));
    }

    public function getType(): string
    {
        return 'text';
    }
}