<?php

namespace App\Validation\Custom;

use App\Framework\Validation\Rules\BaseValidationRule;

class ImageLayoutRule extends BaseValidationRule
{
    private $allowedLayouts = [
        'full',
        'center',
        'left',
        'right',
        'responsive',
        'thumbnail',
        'hero',
        'banner',
        'inline',
        'float-left',
        'float-right'
    ];

    public function validate($value, array $data = []): bool
    {
        if ($value === null || $value === '') {
            return true; // Layout is optional, defaults to 'full'
        }

        return in_array($value, $this->allowedLayouts, true);
    }

    protected function getDefaultMessage(): string
    {
        return 'The image layout must be one of: ' . implode(', ', $this->allowedLayouts);
    }

    public function getAllowedLayouts(): array
    {
        return $this->allowedLayouts;
    }

    public function isResponsiveLayout(string $layout): bool
    {
        return in_array($layout, ['full', 'responsive', 'center', 'hero', 'banner']);
    }

    public function isFloatingLayout(string $layout): bool
    {
        return in_array($layout, ['left', 'right', 'float-left', 'float-right']);
    }

    public function getLayoutDisplayName(string $layout): string
    {
        $displayNames = [
            'full' => 'Full Width',
            'center' => 'Centered',
            'left' => 'Aligned Left',
            'right' => 'Aligned Right',
            'responsive' => 'Responsive',
            'thumbnail' => 'Thumbnail',
            'hero' => 'Hero Image',
            'banner' => 'Banner',
            'inline' => 'Inline',
            'float-left' => 'Float Left',
            'float-right' => 'Float Right'
        ];

        return $displayNames[$layout] ?? ucfirst($layout);
    }

    public function getLayoutCssClass(string $layout): string
    {
        return 'image-layout-' . $layout;
    }

    public function addAllowedLayout(string $layout): void
    {
        $layout = strtolower(trim($layout));
        if (!in_array($layout, $this->allowedLayouts)) {
            $this->allowedLayouts[] = $layout;
        }
    }
}