<?php

namespace App\Parsers;

use App\Validation\Custom\DividerStyleRule;

class DividerBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'divider';
    }

    public function getValidationRules(): array
    {
        return [
            'style' => [
                new DividerStyleRule()
            ]
        ];
    }

    public function parse(array $data): array
    {
        $style = $data['style'] ?? 'solid';

        return [
            'style' => $style,
            'style_label' => $this->getStyleLabel($style),
            'css_class' => $this->getCssClass($style),
            'is_decorative' => $this->isDecorative($style),
            'thickness' => $this->getThickness($style)
        ];
    }

    private function getStyleLabel(string $style): string
    {
        $labels = [
            'solid' => 'Solid Line',
            'dashed' => 'Dashed Line',
            'dotted' => 'Dotted Line',
            'double' => 'Double Line',
            'thick' => 'Thick Line',
            'thin' => 'Thin Line',
            'decorative' => 'Decorative'
        ];

        return $labels[$style] ?? 'Solid Line';
    }

    private function getCssClass(string $style): string
    {
        return 'divider-' . $style;
    }

    private function isDecorative(string $style): bool
    {
        return in_array($style, ['decorative', 'dotted', 'double']);
    }

    private function getThickness(string $style): string
    {
        $thickness = [
            'thin' => '1px',
            'solid' => '2px',
            'thick' => '4px',
            'double' => '3px',
            'dashed' => '2px',
            'dotted' => '2px',
            'decorative' => '3px'
        ];

        return $thickness[$style] ?? '2px';
    }

    public function generateHtml(array $parsedData): string
    {
       return '';
    }
}