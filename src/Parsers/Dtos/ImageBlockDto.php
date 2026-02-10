<?php

namespace App\Parsers\Dtos;

final class ImageBlockDto extends BaseBlockDto
{
    private const ALLOWED_LAYOUTS = ['full', 'responsive', 'fluid', 'fixed'];
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
        public ?int    $imageId
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

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
            trim($data['credit']),
            $data['image_rights'],
            trim($data['linkUrl']),
            (bool)$data['noFollow'],
            (bool)$data['sponsored'],
            (bool)$data['openInNewTab'],
            self::validateEnum($data['layout'], self::ALLOWED_LAYOUTS, 'full', 'layout'),
            self::validateEnum($data['alignment'], self::ALLOWED_ALIGNMENTS, 'fullscreen', 'alignment'),
            $data['endorsements'],
            self::validateEnum($data['context'], self::ALLOWED_CONTEXTS, 'default', 'context'),
            $data['image_id']
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
            'should_display_credit' => !empty($this->credit) && $this->shouldDisplayCredit()
        ];
    }

    private function shouldDisplayCredit(): bool
    {
        if (empty($this->imageRights)) {
            return !empty($this->credit);
        }

        // Map image rights to attribution requirement
        $requiresAttribution = [
            'cc-by',
            'cc-by-sa',
            'cc-by-nc',
            'cc-by-nc-sa',
            'cc-by-nd',
            'cc-by-nc-nd'
        ];

        return in_array(strtolower($this->imageRights), $requiresAttribution);
    }

    public function getType(): string
    {
        return 'image';
    }
}