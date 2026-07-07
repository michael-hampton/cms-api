<?php

namespace App\Parsers\Dtos;

final class NewsFeedBlockDto extends BaseBlockDto
{
    private const ALLOWED_FEED_TYPES = ['latest', 'featured', 'category', 'custom'];
    private const ALLOWED_LAYOUTS = ['grid', 'list', 'carousel'];

    private const KNOWN_KEYS = ['layout'];

    public function __construct(
        public string $feedType,
        public string $layout,
        public int    $limit,
        public ?int   $categoryId,
        public bool   $showImages,
        public bool   $showExcerpts,
        public bool   $showDates,
        public array  $customArticleIds,
        public string $title,
        public string $subtitle,
        public int    $columns,
        public bool   $showAuthor,
        public bool   $showCategory,
        public bool   $showReadTime,
        public array  $items,
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'feedType' => 'latest',
            'layout' => 'grid',
            'limit' => 5,
            'categoryId' => null,
            'showImages' => true,
            'showExcerpts' => true,
            'showDates' => true,
            'customArticleIds' => []
        ]);

        return new self(
            self::validateEnum($data['feedType'], self::ALLOWED_FEED_TYPES, 'latest', 'feedType'),
            self::validateEnum($data['layout'], self::ALLOWED_LAYOUTS, 'grid', 'layout'),
            (int)$data['limit'],
            $data['categoryId'],
            (bool)$data['showImages'] ?? false,
            (bool)$data['showExcerpts'] ?? false,
            (bool)$data['showDates'] ?? false,
            $data['customArticleIds'],
            $data['title'],
            $data['subtitle'] ?? '',
            (int)$data['columns'],
            $data['showAuthor'] ?? false,
            $data['showCategory'] ?? false,
            $data['showReadTime'] ?? false,
            $data['items']
        );
    }

    public function toArray(): array
    {
        return [
            'feedType' => $this->feedType,
            'layout' => $this->layout,
            'limit' => $this->limit,
            'categoryId' => $this->categoryId,
            'showImages' => $this->showImages,
            'showExcerpts' => $this->showExcerpts,
            'showDates' => $this->showDates,
            'customArticleIds' => $this->customArticleIds,
            'is_custom_feed' => $this->feedType === 'custom',
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'columns' => $this->columns,
            'showAuthor' => $this->showAuthor,
            'showCategory' => $this->showCategory,
            'showReadTime' => $this->showReadTime,
            'items' => $this->items,
        ];
    }

    public function getType(): string
    {
        return 'news-feed';
    }
}