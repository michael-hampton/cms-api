<?php

namespace App\Parsers\Dtos;

use InvalidArgumentException;

final class CtaBlockDto extends BaseBlockDto
{
    private const KNOWN_KEYS = [
        'text', 'url', 'noFollow', 'sponsored', 'openInNewTab',
        'style', 'size', 'alignment', 'context'
    ];

    private const ALLOWED_STYLES = ['primary', 'secondary', 'outline', 'ghost'];
    private const ALLOWED_SIZES = ['small', 'medium', 'large'];
    private const ALLOWED_ALIGNMENTS = ['left', 'center', 'right'];
    private const MAX_TEXT_LENGTH = 100;

    public function __construct(
        public string $text,
        public string $url,
        public bool   $noFollow,
        public bool   $sponsored,
        public bool   $openInNewTab,
        public string $style,
        public string $size,
        public string $alignment,
        public string $context,
        public array  $linkAttributes
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'text' => 'Click Here',
            'url' => '',
            'noFollow' => false,
            'sponsored' => false,
            'openInNewTab' => false,
            'style' => 'primary',
            'size' => 'medium',
            'alignment' => 'center',
            'context' => 'default'
        ]);

        $text = trim($data['text']);
        if (empty($text)) {
            throw new InvalidArgumentException('CTA text is required');
        }

        if (strlen($text) > self::MAX_TEXT_LENGTH) {
            $text = substr($text, 0, self::MAX_TEXT_LENGTH);
        }

        $url = trim($data['url']);
        if (empty($url)) {
            throw new InvalidArgumentException('CTA URL is required');
        }

        $style = self::validateEnum($data['style'], self::ALLOWED_STYLES, 'primary', 'style');
        $size = self::validateEnum($data['size'], self::ALLOWED_SIZES, 'medium', 'size');
        $alignment = self::validateEnum($data['alignment'], self::ALLOWED_ALIGNMENTS, 'center', 'alignment');

        $linkAttributes = self::buildLinkAttributes(
            (bool)$data['noFollow'],
            (bool)$data['sponsored'],
            (bool)$data['openInNewTab']
        );

        return new self(
            $text,
            $url,
            (bool)$data['noFollow'],
            (bool)$data['sponsored'],
            (bool)$data['openInNewTab'],
            $style,
            $size,
            $alignment,
            $data['context'],
            $linkAttributes
        );
    }

    private static function buildLinkAttributes(bool $noFollow, bool $sponsored, bool $openInNewTab): array
    {
        $attributes = [];

        if ($openInNewTab) {
            $attributes['target'] = '_blank';
            $attributes['rel'] = 'noopener noreferrer';
        }

        $relValues = [];
        if ($noFollow) $relValues[] = 'nofollow';
        if ($sponsored) $relValues[] = 'sponsored';

        if (!empty($relValues)) {
            if (isset($attributes['rel'])) {
                $attributes['rel'] .= ' ' . implode(' ', $relValues);
            } else {
                $attributes['rel'] = implode(' ', $relValues);
            }
        }

        return $attributes;
    }

    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'url' => $this->url,
            'noFollow' => $this->noFollow,
            'sponsored' => $this->sponsored,
            'openInNewTab' => $this->openInNewTab,
            'style' => $this->style,
            'size' => $this->size,
            'alignment' => $this->alignment,
            'context' => $this->context,
            'link_attributes' => $this->linkAttributes
        ];
    }

    public function getType(): string
    {
        return 'cta';
    }
}