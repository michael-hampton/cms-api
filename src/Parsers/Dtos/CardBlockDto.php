<?php

namespace App\Parsers\Dtos;

final class CardBlockDto extends BaseBlockDto
{
    private const ALLOWED_BUTTON_TYPES = ['primary', 'secondary', 'text'];
    private const ALLOWED_CONTEXTS = ['default', 'sidebar'];
    private const ALLOWED_LAYOUTS = ['full', 'compact'];
    private const ALLOWED_ALIGNMENTS = ['left', 'center', 'right'];

    private const KNOWN_KEYS = [
        'title', 'image', 'endorsement', 'description', 'linkUrl', 'buttonType',
        'buttonText', 'noFollow', 'sponsored', 'openInNewTab', 'sponsorDeclaration',
        'context', 'layout', 'alignment', 'itemsPerRow'
    ];

    public function __construct(
        public string $title,
        public ?array $image,
        public ?array $endorsement,
        public string $description,
        public string $linkUrl,
        public string $buttonType,
        public string $buttonText,
        public bool   $noFollow,
        public bool   $sponsored,
        public bool   $openInNewTab,
        public ?array $sponsorDeclaration,
        public string $context,
        public string $layout,
        public string $alignment,
        public int    $itemsPerRow
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'title' => '',
            'image' => null,
            'endorsement' => null,
            'description' => '',
            'linkUrl' => '',
            'buttonType' => 'primary',
            'buttonText' => 'Learn More',
            'noFollow' => false,
            'sponsored' => false,
            'openInNewTab' => false,
            'sponsorDeclaration' => null,
            'context' => 'default',
            'layout' => 'full',
            'alignment' => 'center',
            'itemsPerRow' => 3
        ]);

        $itemsPerRow = (int)$data['itemsPerRow'];
        if ($itemsPerRow < 1 || $itemsPerRow > 4) {
            $itemsPerRow = 3;
        }

        return new self(
            trim($data['title']),
            self::parseImage($data['image']),
            self::parseImage($data['endorsement']),
            trim($data['description']),
            trim($data['linkUrl']),
            self::validateEnum($data['buttonType'], self::ALLOWED_BUTTON_TYPES, 'primary', 'buttonType'),
            trim($data['buttonText']),
            (bool)$data['noFollow'],
            (bool)$data['sponsored'],
            (bool)$data['openInNewTab'],
            self::parseSponsorDeclaration($data['sponsorDeclaration']),
            self::validateEnum($data['context'], self::ALLOWED_CONTEXTS, 'default', 'context'),
            self::validateEnum($data['layout'], self::ALLOWED_LAYOUTS, 'full', 'layout'),
            self::validateEnum($data['alignment'], self::ALLOWED_ALIGNMENTS, 'center', 'alignment'),
            $itemsPerRow
        );
    }

    private static function parseImage(?array $image): ?array
    {
        if (empty($image) || empty($image['src'])) {
            return null;
        }

        return [
            'id' => $image['id'] ?? '',
            'src' => trim($image['src']),
            'name' => trim($image['name'] ?? ''),
            'alt' => trim($image['alt'] ?? ''),
            'caption' => trim($image['caption'] ?? '')
        ];
    }

    private static function parseSponsorDeclaration(?array $declaration): ?array
    {
        if (empty($declaration)) {
            return null;
        }

        $hasContent = !empty($declaration['sponsoredText']) ||
            !empty($declaration['sponsorName']) ||
            !empty($declaration['sponsorLogo']);

        if (!$hasContent) {
            return null;
        }

        return [
            'sponsoredText' => trim($declaration['sponsoredText'] ?? ''),
            'sponsorName' => trim($declaration['sponsorName'] ?? ''),
            'sponsorLogo' => self::parseImage($declaration['sponsorLogo'] ?? null)
        ];
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'image' => $this->image,
            'endorsement' => $this->endorsement,
            'description' => $this->description,
            'linkUrl' => $this->linkUrl,
            'buttonType' => $this->buttonType,
            'buttonText' => $this->buttonText,
            'noFollow' => $this->noFollow,
            'sponsored' => $this->sponsored,
            'openInNewTab' => $this->openInNewTab,
            'sponsorDeclaration' => $this->sponsorDeclaration,
            'context' => $this->context,
            'layout' => $this->layout,
            'alignment' => $this->alignment,
            'itemsPerRow' => $this->itemsPerRow
        ];
    }

    public function getType(): string
    {
        return 'card';
    }
}