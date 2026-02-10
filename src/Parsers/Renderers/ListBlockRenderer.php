<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\ListBlockDto;

class ListBlockRenderer extends BaseBlockRenderer
{
    protected function getSupportedType(): string
    {
        return 'list';
    }

    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof ListBlockDto) {
            return '';
        }

        $listTag = $dto->listType === 'ol' ? 'ol' : 'ul';
        $schemaClass = $dto->schemaType !== 'none' ? " list-schema-{$dto->schemaType}" : '';
        $contextClass = $dto->context === 'sidebar' ? ' list-sidebar' : '';

        $html = "<div class=\"list-block{$schemaClass}{$contextClass}\">";

        $listAttrs = '';
        if ($dto->listType === 'ol' && $dto->startIndex !== 1) {
            $listAttrs = " start=\"{$dto->startIndex}\"";
        }

        // Add schema markup if needed
        if ($dto->schemaType === 'steps') {
            $html .= '<div itemscope itemtype="https://schema.org/HowTo">';
        } elseif ($dto->schemaType === 'ingredients') {
            $html .= '<div itemscope itemtype="https://schema.org/Recipe">';
        }

        $html .= "<{$listTag} class=\"list-items\"{$listAttrs}>";

        foreach ($dto->items as $index => $item) {
            $html .= $this->renderItem($item, $dto->schemaType);
        }

        $html .= "</{$listTag}>";

        if ($dto->schemaType === 'steps' || $dto->schemaType === 'ingredients') {
            $html .= '</div>';
        }

        $html .= "</div>";

        return $html;
    }

    private function renderItem(string $item, string $schemaType): string
    {
        // Allow safe HTML tags
        $allowed = '<a><strong><em><br><b><i>';
        $sanitized = strip_tags($item, $allowed);

        if ($schemaType === 'steps') {
            return '<li class="list-item" itemprop="step" itemscope itemtype="https://schema.org/HowToStep">' .
                '<span itemprop="text">' . $sanitized . '</span>' .
                '</li>';
        }

        if ($schemaType === 'ingredients') {
            return '<li class="list-item" itemprop="recipeIngredient">' . $sanitized . '</li>';
        }

        return "<li class=\"list-item\">{$sanitized}</li>";
    }
}