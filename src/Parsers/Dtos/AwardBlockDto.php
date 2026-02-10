<?php

namespace App\Parsers\Dtos;

final class AwardBlockDto extends BaseBlockDto
{
    private const ALLOWED_CONTEXTS = ['default', 'sidebar'];

    private const KNOWN_KEYS = [
        'subcategory', 'productName', 'winner', 'rating'
    ];

    public function __construct(
        public string $subcategory,
        public string $productName,
        public ?array $image,
        public string $caption,
        public string $alt,
        public bool   $winner,
        public string $strapline,
        public float  $rating,
        public string $context
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'subcategory' => '',
            'productName' => '',
            'image' => null,
            'caption' => '',
            'alt' => '',
            'winner' => false,
            'strapline' => '',
            'rating' => 0.0,
            'context' => 'default'
        ]);

        $rating = (float)$data['rating'];
        if ($rating < 0) $rating = 0;
        if ($rating > 5) $rating = 5;

        return new self(
            trim($data['subcategory']),
            trim($data['productName']),
            $data['image'],
            trim($data['caption']),
            trim($data['alt']),
            (bool)$data['winner'],
            trim($data['strapline']),
            $rating,
            self::validateEnum($data['context'], self::ALLOWED_CONTEXTS, 'default', 'context')
        );
    }

    public function toArray(): array
    {
        return [
            'subcategory' => $this->subcategory,
            'context' => $this->context,
            'productName' => $this->productName,
            'image' => $this->image,
            'caption' => $this->caption,
            'alt' => $this->alt,
            'winner' => $this->winner,
            'strapline' => $this->strapline,
            'rating' => $this->rating,
            'caption_word_count' => str_word_count($this->caption),
            'strapline_word_count' => str_word_count($this->strapline),
            'formatted_caption' => nl2br(htmlspecialchars($this->caption)),
            'formatted_strapline' => nl2br(htmlspecialchars($this->strapline)),
        ];
    }

    public function getType(): string
    {
        return 'award';
    }
}