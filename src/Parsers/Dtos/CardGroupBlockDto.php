<?php

namespace App\Parsers\Dtos;

final class CardGroupBlockDto extends BaseBlockDto
{
    private const ALLOWED_GAPS = ['small', 'medium', 'large'];

    private const KNOWN_KEYS = ['itemsPerRow', 'cards'];

    public function __construct(
        public int    $itemsPerRow,
        public string $gap,
        public array  $cards
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'itemsPerRow' => 3,
            'gap' => 'medium',
            'cards' => []
        ]);

        $itemsPerRow = (int)$data['itemsPerRow'];
        if ($itemsPerRow < 1 || $itemsPerRow > 4) {
            $itemsPerRow = 3;
        }

        $cards = [];
        if (isset($data['cards']) && is_array($data['cards'])) {
            foreach ($data['cards'] as $card) {
                if (self::hasCardContent($card)) {
                    $cards[] = CardBlockDto::fromArray($card);
                }
            }
        }

        return new self(
            $itemsPerRow,
            self::validateEnum($data['gap'], self::ALLOWED_GAPS, 'medium', 'gap'),
            $cards
        );
    }

    private static function hasCardContent(array $card): bool
    {
        return !empty($card['title']) ||
            !empty($card['description']) ||
            !empty($card['image']['src']);
    }

    public function toArray(): array
    {
        return [
            'itemsPerRow' => $this->itemsPerRow,
            'gap' => $this->gap,
            'cards' => array_map(fn($card) => $card->toArray(), $this->cards)
        ];
    }

    public function getType(): string
    {
        return 'card-group';
    }
}