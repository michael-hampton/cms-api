<?php

namespace App\Parsers\Dtos;

final class PageGridBlockDto extends BaseBlockDto
{
    private const ALLOWED_COLUMNS = [2, 3, 4];
    private const KNOWN_KEYS = ['columns', 'items', 'showImages'];

    public function __construct(
        public int    $columns,
        public array  $items,
        public bool   $showImages,
        public string $title,
        public string $subtitle,
        public string $layout,
        public bool   $showExcerpt,
        public bool   $showFeatures,
        public bool   $showActions,
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'columns' => 3,
            'items' => [],
            'showImages' => true
        ]);

        $columns = (int)$data['columns'];
        if (!in_array($columns, self::ALLOWED_COLUMNS)) {
            $columns = 3;
        }

        $items = [];
        foreach ($data['items'] as $item) {
            if (!is_array($item)) continue;

            $items[] = [
                'title' => trim($item['title'] ?? ''),
                'description' => trim($item['description'] ?? ''),
                'url' => trim($item['url'] ?? ''),
                'image' => $item['image'] ?? null
            ];
        }

        return new self(
            $columns,
            $items,
            (bool)$data['showImages'],
            $data['title'],
            $data['subtitle'] ?? '',
            $data['layout'],
            $data['showExcerpt'],
            $data['showFeatures'] ?? false,
            $data['showActions'] ?? false

        );
    }

    public function toArray(): array
    {
        return [
            'columns' => $this->columns,
            'items' => $this->items,
            'showImages' => $this->showImages,
            'items_count' => count($this->items),
            'grid_class' => 'grid-cols-' . $this->columns,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'layout' => $this->layout,
            'showExcerpt' => $this->showExcerpt,
            'showFeatures' => $this->showFeatures,
            'showActions' => $this->showActions
        ];
    }

    public function getType(): string
    {
        return 'page-grid';
    }
}