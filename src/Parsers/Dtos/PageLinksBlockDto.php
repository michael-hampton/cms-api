<?php

namespace App\Parsers\Dtos;

final class PageLinksBlockDto extends BaseBlockDto
{
    private const ALLOWED_LAYOUTS = ['grid', 'list', 'compact'];
    private const ALLOWED_COLUMNS = [2, 3, 4, 5];
    private const ALLOWED_CONTEXTS = ['default', 'sidebar'];

    private const KNOWN_KEYS = [
        'layout', 'columns', 'showImages', 'showDescriptions', 'links'
    ];

    public function __construct(
        public string $title,
        public string $layout,
        public int    $columns,
        public bool   $showImages,
        public bool   $showDescriptions,
        public array  $links,
        public string $context
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'title' => '',
            'layout' => 'grid',
            'columns' => 3,
            'showImages' => true,
            'showDescriptions' => true,
            'links' => [],
            'context' => 'default'
        ]);

        $validatedLinks = [];
        foreach ($data['links'] ?? [] as $link) {
            if (empty($link['title'])) {
                continue;
            }

            $validatedLinks[] = [
                'title' => trim($link['title']),
                'description' => trim($link['description'] ?? ''),
                'imageUrl' => $link['imageUrl'] ?? '',
                'imageId' => $link['imageId'] ?? null,
                'pageUrl' => $link['pageUrl'] ?? '',
                'pageId' => $link['pageId'] ?? null,
                'icon' => $link['icon'] ?? ''
            ];
        }

        $columns = (int)$data['columns'];
        if (!in_array($columns, self::ALLOWED_COLUMNS, true)) {
            $columns = 3;
        }

        return new self(
            trim($data['title']),
            self::validateEnum($data['layout'], self::ALLOWED_LAYOUTS, 'grid', 'layout'),
            $columns,
            (bool)$data['showImages'],
            (bool)$data['showDescriptions'],
            $validatedLinks,
            self::validateEnum($data['context'], self::ALLOWED_CONTEXTS, 'default', 'context')
        );
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'layout' => $this->layout,
            'columns' => $this->columns,
            'showImages' => $this->showImages,
            'showDescriptions' => $this->showDescriptions,
            'links' => $this->links,
            'context' => $this->context,
            'total_links' => count($this->links)
        ];
    }

    public function getType(): string
    {
        return 'page-links';
    }
}