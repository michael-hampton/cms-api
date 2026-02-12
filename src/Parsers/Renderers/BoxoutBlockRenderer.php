<?php

namespace App\Parsers\Renderers;


use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\BoxoutBlockDto;

class BoxoutBlockRenderer extends BaseBlockRenderer
{
    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof BoxoutBlockDto) {
            throw new \InvalidArgumentException('Expected BoxoutBlockDto');
        }

        $alignmentClass = 'note-align-' . $dto->alignment;
        $contextClass = $dto->context === 'sidebar' ? ' note-sidebar' : '';

        $html = "<div class=\"note-block {$alignmentClass}{$contextClass}\">";

        if (!empty($dto->image)) {
            $html .= "<div class=\"note-image\">";
            $html .= "<img src=\"{$dto->image['src']}\" alt=\"" . $this->escape($dto->title) . "\" class=\"note-img\">";
            $html .= "</div>";
        }

        $html .= "<div class=\"note-content\">";
        $html .= "<h4 class=\"note-title\">" . $this->escape($dto->title) . "</h4>";

        foreach ($dto->paragraphs as $paragraph) {
            $html .= "<p class=\"note-paragraph\">" . $this->escapeWithBreaks($paragraph) . "</p>";
        }

        if (!empty($dto->linkUrl)) {

            $linkAttrs = '';
            if ($dto->openInNewTab) {
                $linkAttrs .= ' target="_blank"';
            }

            $relValues = [];
            if ($dto->noFollow) $relValues[] = 'nofollow';
            if ($dto->sponsored) $relValues[] = 'sponsored';
            if ($dto->openInNewTab) $relValues[] = 'noopener noreferrer';

            if (!empty($relValues)) {
                $linkAttrs .= ' rel="' . implode(' ', array_unique($relValues)) . '"';
            }

            $html .= "<a href=\"{$dto->linkUrl}\"{$linkAttrs} class=\"note-link\">";
            $html .= $this->escape($dto->linkText);

            if ($dto->sponsored) {
                $html .= "<span class=\"sponsored-badge\">Sponsored</span>";
            }

            $html .= "</a>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    protected function getSupportedType(): string
    {
        return 'note';
    }
}