<?php

namespace App\Parsers\Dtos;

use InvalidArgumentException;

final class AccordionBlockDto extends BaseBlockDto
{
    private const ALLOWED_THEMES = ['light', 'dark', 'colored', 'minimal'];
    private const ALLOWED_CONTEXTS = ['default', 'sidebar'];
    private const MIN_VISIBLE_ITEMS = 1;
    private const MAX_VISIBLE_ITEMS = 50;

    // All known fields for validation
    private const KNOWN_KEYS = [
        'title', 'introContent', 'items', 'allowMultipleOpen',
        'openFirstByDefault', 'theme', 'context', 'visibleItemsCount'
    ];

    public function __construct(
        public string $title,
        public string $introContent,
        public array  $items,
        public bool   $allowMultipleOpen,
        public bool   $openFirstByDefault,
        public string $theme,
        public string $context,
        public int    $visibleItemsCount
    )
    {
    }

    public static function fromArray(array $data): self
    {
        // Debug check for unknown fields
        self::validateKeys($data, self::KNOWN_KEYS);

        // Apply defaults
        $data = self::applyDefaults($data, [
            'title' => '',
            'introContent' => '',
            'items' => [],
            'allowMultipleOpen' => false,
            'openFirstByDefault' => true,
            'theme' => 'light',
            'context' => 'default',
            'visibleItemsCount' => 5
        ]);

        // Normalize and validate
        $title = trim($data['title']);
        $introContent = trim($data['introContent']);
        $items = self::validateItems($data['items'], $data['openFirstByDefault']);
        $allowMultipleOpen = (bool)$data['allowMultipleOpen'];
        $openFirstByDefault = (bool)$data['openFirstByDefault'];
        $theme = self::validateEnum(
            $data['theme'],
            self::ALLOWED_THEMES,
            'light',
            'theme'
        );
        $context = self::validateEnum(
            $data['context'],
            self::ALLOWED_CONTEXTS,
            'default',
            'context'
        );
        $visibleItemsCount = self::validateRange(
            (int)$data['visibleItemsCount'],
            self::MIN_VISIBLE_ITEMS,
            self::MAX_VISIBLE_ITEMS,
            'visibleItemsCount'
        );

        return new self(
            $title,
            $introContent,
            $items,
            $allowMultipleOpen,
            $openFirstByDefault,
            $theme,
            $context,
            $visibleItemsCount
        );
    }

    private static function validateItems(array $items, bool $openFirstByDefault): array
    {
        if (!is_array($items)) {
            throw new InvalidArgumentException('Items must be an array');
        }

        $validatedItems = [];

        foreach ($items as $index => $item) {
            // Structural validation
            if (!isset($item['question']) || !isset($item['answer'])) {
                if (self::$debugMode) {
                    error_log("WARNING: Item at index {$index} missing question or answer, skipping");
                }
                continue;
            }

            if (empty($item['question']) || empty($item['answer'])) {
                continue;
            }

            $order = isset($item['order']) ? (int)$item['order'] : $index;

            $validatedItems[] = [
                'question' => trim($item['question']),
                'answer' => trim($item['answer']),
                'isOpen' => $index === 0 && $openFirstByDefault ? true : (bool)($item['isOpen'] ?? false),
                'order' => $order
            ];
        }

        // Sort by order
        usort($validatedItems, fn($a, $b) => $a['order'] <=> $b['order']);

        // Reindex
        foreach ($validatedItems as $index => &$item) {
            $item['order'] = $index;
        }

        return $validatedItems;
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'introContent' => $this->introContent,
            'items' => $this->items,
            'allowMultipleOpen' => $this->allowMultipleOpen,
            'openFirstByDefault' => $this->openFirstByDefault,
            'context' => $this->context,
            'theme' => $this->theme,
            'visibleItemsCount' => $this->visibleItemsCount,
            'total_items' => count($this->items)
        ];
    }

    public function getType(): string
    {
        return 'accordion';
    }
}