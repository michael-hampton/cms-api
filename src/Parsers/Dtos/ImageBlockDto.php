<?php

namespace App\Parsers\Dtos;

use App\Enums\ImageRights;
use App\Models\Image;

final class ImageBlockDto extends BaseBlockDto
{
    private const ALLOWED_LAYOUTS = ['full', 'responsive', 'fluid', 'fixed', 'inline'];
    private const ALLOWED_ALIGNMENTS = ['fullscreen', 'left', 'right', 'center'];
    private const ALLOWED_CONTEXTS = ['default', 'sidebar'];

    private const KNOWN_KEYS = [
        'src', 'alt'
    ];

    public function __construct(
        public string  $src,
        public string  $caption,
        public string  $alt,
        public string  $credit,
        public ?string $imageRights,
        public string  $linkUrl,
        public bool    $noFollow,
        public bool    $sponsored,
        public bool    $openInNewTab,
        public string  $layout,
        public string  $alignment,
        public array   $endorsements,
        public string  $context,
        public ?int $imageId,
        public bool $shouldDisplayCredit = false
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $imageData = self::getImageData($data['image_id'] ?? null);

        $credit = trim($imageData['credit'] ?? '');
        $imageRights = $imageData['image_rights'] ?? null;

        $data = self::applyDefaults($data, [
            'src' => '',
            'caption' => '',
            'alt' => '',
            'credit' => '',
            'image_rights' => null,
            'linkUrl' => '',
            'noFollow' => false,
            'sponsored' => false,
            'openInNewTab' => false,
            'layout' => 'full',
            'alignment' => 'fullscreen',
            'endorsements' => [],
            'context' => 'default',
            'image_id' => null
        ]);

        return new self(
            trim($data['src']),
            trim($data['caption']),
            trim($data['alt']),
            trim($credit ?: $data['credit']),
            $imageRights,
            trim($data['linkUrl']),
            (bool)$data['noFollow'],
            (bool)$data['sponsored'],
            (bool)$data['openInNewTab'],
            self::validateEnum($data['layout'], self::ALLOWED_LAYOUTS, 'full', 'layout'),
            self::validateEnum($data['alignment'], self::ALLOWED_ALIGNMENTS, 'fullscreen', 'alignment'),
            $data['endorsements'],
            self::validateEnum($data['context'], self::ALLOWED_CONTEXTS, 'default', 'context'),
            $data['image_id'],
            self::shouldDisplayCredit($imageRights, $credit)
            || ($data['should_display_credit'] ?? false)
        );
    }

    public function toArray(): array
    {
        $linkAttributes = [];
        if ($this->openInNewTab) {
            $linkAttributes['target'] = '_blank';
            $linkAttributes['rel'] = 'noopener noreferrer';
        }

        $relValues = [];
        if ($this->noFollow) $relValues[] = 'nofollow';
        if ($this->sponsored) $relValues[] = 'sponsored';

        if (!empty($relValues)) {
            if (isset($linkAttributes['rel'])) {
                $linkAttributes['rel'] .= ' ' . implode(' ', $relValues);
            } else {
                $linkAttributes['rel'] = implode(' ', $relValues);
            }
        }

        return [
            'src' => $this->src,
            'caption' => $this->caption,
            'alt' => $this->alt,
            'credit' => $this->credit,
            'image_rights' => $this->imageRights,
            'linkUrl' => $this->linkUrl,
            'noFollow' => $this->noFollow,
            'sponsored' => $this->sponsored,
            'openInNewTab' => $this->openInNewTab,
            'layout' => $this->layout,
            'alignment' => $this->alignment,
            'endorsements' => $this->endorsements,
            'context' => $this->context,
            'has_caption' => !empty($this->caption),
            'has_credit' => !empty($this->credit),
            'has_link' => !empty($this->linkUrl),
            'formatted_caption' => htmlspecialchars($this->caption),
            'formatted_alt' => htmlspecialchars($this->alt),
            'formatted_credit' => htmlspecialchars($this->credit),
            'caption_word_count' => str_word_count($this->caption),
            'alt_word_count' => str_word_count($this->alt),
            'link_attributes' => $linkAttributes,
            'layout_css_class' => 'image-layout-' . $this->layout,
            'alignment_css_class' => 'image-align-' . $this->alignment,
            'has_endorsements' => !empty($this->endorsements),
            'should_display_credit' => $this->shouldDisplayCredit,
            'endorsement_positions' => array_keys($this->endorsements),
        ];
    }

    public function getType(): string
    {
        return 'image';
    }

    private static function getImageData(?int $imageId): array
    {
        if (!$imageId) {
            return [];
        }

        $image = Image::find($imageId);
        if (!$image) {
            return [];
        }

        return [
            'credit' => $image->credit,
            'image_rights' => $image->image_rights,
        ];
    }

    private static function shouldDisplayCredit(?string $imageRights, string $credit): bool
    {
        // Always display credit if it exists and attribution is required
        if (empty($credit)) {
            return false;
        }

        if (empty($imageRights)) {
            return !empty($credit); // Display if credit exists but no rights specified
        }

        try {
            $rights = ImageRights::from($imageRights);
            return $rights->requiresAttribution();
        } catch (\ValueError $e) {
            return !empty($credit); // Fallback to showing credit if invalid rights
        }
    }
}