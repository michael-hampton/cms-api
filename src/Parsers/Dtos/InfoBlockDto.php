<?php

namespace App\Parsers\Dtos;

use App\Enums\Blocks\InfoType;
use InvalidArgumentException;

final class InfoBlockDto extends BaseBlockDto
{
    private const KNOWN_KEYS = ['infoType', 'description', 'context'];
    private const MAX_DESCRIPTION_LENGTH = 2000;

    public function __construct(
        public string $infoType,
        public string $description,
        public string $context
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'infoType' => 'info',
            'description' => '',
            'context' => 'default'
        ]);

        $description = trim($data['description']);
        if (empty($description)) {
            throw new InvalidArgumentException('Info description is required');
        }

        if (strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
            $description = substr($description, 0, self::MAX_DESCRIPTION_LENGTH);
        }

        // Validate infoType is valid enum
        try {
            $infoTypeEnum = InfoType::from($data['infoType']);
            $infoType = $infoTypeEnum->value;
        } catch (\ValueError $e) {
            if (self::$debugMode) {
                error_log("WARNING: Invalid info type '{$data['infoType']}', using 'info'");
            }
            $infoType = 'info';
        }

        return new self($infoType, $description, $data['context']);
    }

    public function getIcon(): string
    {
        return match ($this->infoType) {
            'info' => 'ℹ️',
            'warning' => '⚠️',
            'tip' => '💡',
            'note' => '📝',
            'ingredients' => '🥗',
            'recipe' => '👨‍🍳',
            'instructions' => '📋',
            'update' => '📝',
            default => 'ℹ️'
        };
    }

    public function toArray(): array
    {
        return [
            'infoType' => $this->infoType,
            'description' => $this->description,
            'context' => $this->context,
            'icon' => $this->getIcon(),
            'word_count' => str_word_count($this->description)
        ];
    }

    public function getType(): string
    {
        return 'info';
    }
}