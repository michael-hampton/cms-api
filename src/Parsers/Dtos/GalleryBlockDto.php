<?php

namespace App\Parsers\Dtos;

final class GalleryBlockDto extends BaseBlockDto
{
    private const ALLOWED_LAYOUTS = ['carousel', 'grid', 'list', 'masonry', 'slider'];
    private const KNOWN_KEYS = ['layout', 'slides'];

    public function __construct(
        public string $layout,
        public array  $slides
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'layout' => 'carousel',
            'slides' => []
        ]);

        $slides = [];
        foreach ($data['slides'] as $slide) {
            if (!is_array($slide)) continue;

            $title = trim($slide['title'] ?? '');
            $description = trim($slide['description'] ?? '');
            $caption = trim($slide['caption'] ?? '');

            if (empty($title) && empty($description) && empty($slide['image'])) {
                continue;
            }

            $slides[] = [
                'title' => $title,
                'caption' => $caption,
                'description' => $description,
                'image' => $slide['image'] ?? null,
                'alt' => trim($slide['alt'] ?? ''),
                'link' => $slide['link'] ?? null,
                'noFollow' => (bool)($slide['noFollow'] ?? false),
                'sponsored' => (bool)($slide['sponsored'] ?? false),
                'openInNewTab' => (bool)($slide['openInNewTab'] ?? false)
            ];
        }

        return new self(
            self::validateEnum($data['layout'], self::ALLOWED_LAYOUTS, 'carousel', 'layout'),
            $slides
        );
    }

    public function toArray(): array
    {
        return [
            'layout' => $this->layout,
            'slides' => $this->slides,
            'slide_count' => count($this->slides),
            'total_word_count' => $this->calculateTotalWordCount($this->slides),
        ];
    }

    public function getType(): string
    {
        return 'gallery';
    }

    private function calculateTotalWordCount(array $slides): int
    {
        $totalWords = 0;

        foreach ($slides as $slide) {
            $totalWords += str_word_count(strip_tags($slide['title'] . ' ' . $slide['description'] . ' ' . $slide['caption'])) ?? 0;
        }

        return $totalWords;
    }
}