<?php

namespace App\Parsers\Dtos;

use InvalidArgumentException;

final class ProductComparisonBlockDto extends BaseBlockDto
{
    private const KNOWN_KEYS = ['title', 'productA', 'productB', 'comparisons', 'product_a_id', 'product_b_id'];
    private const MAX_TITLE_LENGTH = 255;
    private const MAX_PRODUCT_NAME_LENGTH = 255;

    public function __construct(
        public string $title,
        public string $productA,
        public string $productB,
        public array  $comparisons,
        public ?int   $productAId,
        public ?int   $productBId
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'title' => '',
            'productA' => '',
            'productB' => '',
            'comparisons' => [],
            'product_a_id' => null,
            'product_b_id' => null
        ]);

        $title = trim($data['title']);
        if (empty($title)) {
            throw new InvalidArgumentException('Comparison title is required');
        }

        $productA = trim($data['productA']);
        $productB = trim($data['productB']);

        if (empty($productA) || empty($productB)) {
            throw new InvalidArgumentException('Both product names are required');
        }

        if (strlen($title) > self::MAX_TITLE_LENGTH) {
            $title = substr($title, 0, self::MAX_TITLE_LENGTH);
        }

        if (strlen($productA) > self::MAX_PRODUCT_NAME_LENGTH) {
            $productA = substr($productA, 0, self::MAX_PRODUCT_NAME_LENGTH);
        }

        if (strlen($productB) > self::MAX_PRODUCT_NAME_LENGTH) {
            $productB = substr($productB, 0, self::MAX_PRODUCT_NAME_LENGTH);
        }

        $comparisons = self::parseComparisons($data['comparisons'] ?? []);

        if (empty($comparisons)) {
            throw new InvalidArgumentException('At least one comparison is required');
        }

        return new self(
            $title,
            $productA,
            $productB,
            $comparisons,
            $data['product_a_id'],
            $data['product_b_id']
        );
    }

    private static function parseComparisons(array $comparisons): array
    {
        $parsed = [];

        foreach ($comparisons as $comparison) {
            if (!is_array($comparison)) {
                continue;
            }

            $subtitle = trim($comparison['subtitle'] ?? '');
            $items = $comparison['items'] ?? [];

            if (empty($subtitle) || !is_array($items) || count($items) < 2) {
                continue;
            }

            $parsedItems = [];
            foreach ($items as $item) {
                if (is_array($item) && isset($item['value'])) {
                    $value = trim($item['value']);
                } else {
                    $value = trim((string)$item);
                }
                $parsedItems[] = $value;
            }

            // Ensure exactly 2 items
            while (count($parsedItems) < 2) {
                $parsedItems[] = '';
            }
            $parsedItems = array_slice($parsedItems, 0, 2);

            $parsed[] = [
                'subtitle' => $subtitle,
                'items' => $parsedItems
            ];
        }

        return $parsed;
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'productA' => $this->productA,
            'productB' => $this->productB,
            'comparisons' => $this->comparisons,
            'product_a_id' => $this->productAId,
            'product_b_id' => $this->productBId,
            'comparison_count' => count($this->comparisons)
        ];
    }

    public function getType(): string
    {
        return 'product-comparison';
    }
}