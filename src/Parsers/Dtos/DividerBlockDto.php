<?php

namespace App\Parsers\Dtos;

final class DividerBlockDto extends BaseBlockDto
{
    private const KNOWN_KEYS = ['style'];
    private const ALLOWED_STYLES = ['solid', 'dashed', 'dotted', 'double', 'thick', 'thin', 'decorative'];

    public function __construct(
        public string $style
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'style' => 'solid'
        ]);

        $style = self::validateEnum(
            $data['style'],
            self::ALLOWED_STYLES,
            'solid',
            'style'
        );

        return new self($style);
    }

    public function getStyleLabel(): string
    {
        return match ($this->style) {
            'solid' => 'Solid Line',
            'dashed' => 'Dashed Line',
            'dotted' => 'Dotted Line',
            'double' => 'Double Line',
            'thick' => 'Thick Line',
            'thin' => 'Thin Line',
            'decorative' => 'Decorative',
            default => 'Solid Line'
        };
    }

    public function getCssClass(): string
    {
        return 'divider-' . $this->style;
    }

    public function isDecorative(): bool
    {
        return in_array($this->style, ['decorative', 'dotted', 'double']);
    }

    public function getThickness(): string
    {
        return match ($this->style) {
            'thin' => '1px',
            'solid' => '2px',
            'thick' => '4px',
            'double' => '3px',
            'dashed' => '2px',
            'dotted' => '2px',
            'decorative' => '3px',
            default => '2px'
        };
    }

    public function toArray(): array
    {
        return [
            'style' => $this->style,
            'style_label' => $this->getStyleLabel(),
            'css_class' => $this->getCssClass(),
            'is_decorative' => $this->isDecorative(),
            'thickness' => $this->getThickness()
        ];
    }

    public function getType(): string
    {
        return 'divider';
    }
}