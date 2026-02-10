<?php

namespace App\Parsers\Dtos;

use App\Enums\Blocks\ListType;
use App\Enums\Blocks\SchemaType;
use InvalidArgumentException;

final class ListBlockDto extends BaseBlockDto
{
    private const KNOWN_KEYS = ['listType', 'items'];

    public function __construct(
        public string $listType,
        public ?int   $startIndex,
        public string $schemaType,
        public array  $items,
        public string $context
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'listType' => 'ul',
            'startIndex' => 1,
            'schemaType' => 'none',
            'items' => [],
            'context' => 'default'
        ]);

        if (empty($data['items']) || !is_array($data['items'])) {
            throw new InvalidArgumentException('List items are required');
        }

        // Validate listType
        try {
            $listTypeEnum = ListType::from($data['listType']);
            $listType = $listTypeEnum->value;
        } catch (\ValueError $e) {
            if (self::$debugMode) {
                error_log("WARNING: Invalid list type '{$data['listType']}', using 'ul'");
            }
            $listType = 'ul';
        }

        // Validate schemaType
        try {
            $schemaTypeEnum = SchemaType::from($data['schemaType']);
            $schemaType = $schemaTypeEnum->value;
        } catch (\ValueError $e) {
            if (self::$debugMode) {
                error_log("WARNING: Invalid schema type '{$data['schemaType']}', using 'none'");
            }
            $schemaType = 'none';
        }

        // Parse items
        $items = [];
        foreach ($data['items'] as $item) {
            $trimmed = trim($item);
            if (!empty($trimmed)) {
                $items[] = $trimmed;
            }
        }

        if (empty($items)) {
            throw new InvalidArgumentException('At least one non-empty list item is required');
        }

        $startIndex = $listType === 'ol' ? (int)$data['startIndex'] : null;

        return new self($listType, $startIndex, $schemaType, $items, $data['context']);
    }

    public function toArray(): array
    {
        return [
            'listType' => $this->listType,
            'startIndex' => $this->startIndex,
            'schemaType' => $this->schemaType,
            'items' => $this->items,
            'context' => $this->context,
            'item_count' => count($this->items),
            'total_word_count' => $this->getTotalWordCount()
        ];
    }

    private function getTotalWordCount(): int
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += str_word_count(strip_tags($item));
        }
        return $total;
    }

    public function getType(): string
    {
        return 'list';
    }
}