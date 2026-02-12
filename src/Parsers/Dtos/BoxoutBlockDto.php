<?php

namespace App\Parsers\Dtos;

final class BoxoutBlockDto extends BaseBlockDto
{
    private const ALLOWED_ALIGNMENTS = ['fullscreen', 'left', 'right', 'center', 'centre'];
    private const ALLOWED_CONTEXTS = ['default', 'sidebar'];

    private const KNOWN_KEYS = [
        'title', 'paragraphs', 'alignment',
    ];

    public function __construct(
        public string $title,
        public array  $paragraphs,
        public ?array $image,
        public string $alignment,
        public string $linkUrl,
        public string $linkText,
        public bool   $noFollow,
        public bool   $sponsored,
        public bool   $openInNewTab,
        public string $context
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'title' => '',
            'paragraphs' => [],
            'image' => null,
            'alignment' => 'fullscreen',
            'linkUrl' => '',
            'linkText' => 'Learn More',
            'noFollow' => false,
            'sponsored' => false,
            'openInNewTab' => false,
            'context' => 'default'
        ]);

        $paragraphs = array_filter(array_map('trim', $data['paragraphs'] ?? []), 'strlen');

        return new self(
            trim($data['title']),
            $paragraphs,
            $data['image'],
            self::validateEnum($data['alignment'], self::ALLOWED_ALIGNMENTS, 'fullscreen', 'alignment'),
            $data['linkUrl'],
            $data['linkText'],
            (bool)$data['noFollow'],
            (bool)$data['sponsored'],
            (bool)$data['openInNewTab'],
            self::validateEnum($data['context'], self::ALLOWED_CONTEXTS, 'default', 'context')
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
            'title' => $this->title,
            'paragraphs' => $this->paragraphs,
            'image' => $this->image,
            'formatted_title' => htmlspecialchars($this->title),
            'context' => $this->context,
            'formatted_paragraphs' => array_map(function ($p) {
                return nl2br(htmlspecialchars($p));
            }, $this->paragraphs),
            'word_count' => str_word_count($this->title . ' ' . implode(' ', $this->paragraphs)),
            'has_image' => !empty($this->image),
            'alignment' => $this->alignment,
            'linkUrl' => $this->linkUrl,
            'linkText' => $this->linkText,
            'noFollow' => $this->noFollow,
            'sponsored' => $this->sponsored,
            'openInNewTab' => $this->openInNewTab,
            'has_link' => !empty($this->linkUrl),
            'link_attributes' => $linkAttributes
        ];
    }

    public function getType(): string
    {
        return 'note';
    }
}