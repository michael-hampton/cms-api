<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\BuyingGuideBlockDto;

class BuyingGuideBlockRenderer extends BaseBlockRenderer
{
    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof BuyingGuideBlockDto) {
            throw new \InvalidArgumentException('Expected BuyingGuideBlockDto');
        }

        $html = "<div class=\"buying-guide-block\">";

        if ($dto->sponsored) {
            $html .= "<span class=\"sponsored-badge\">Sponsored</span>";
        }

        if (!empty($dto->image) && !empty($dto->image['src'])) {
            $html .= "<div class=\"buying-guide-image\">";
            $html .= "<img src=\"{$dto->image['src']}\" alt=\"" . $this->escape($dto->title) . "\" class=\"guide-img\">";
            $html .= "</div>";
        }

        $html .= "<div class=\"buying-guide-header\">";
        $html .= "<h3 class=\"buying-guide-title\">" . $this->escape($dto->title) . "</h3>";

        if (!empty($dto->subtitle)) {
            $html .= "<p class=\"buying-guide-subtitle\">" . $this->escape($dto->subtitle) . "</p>";
        }
        $html .= "</div>";

        if (!empty($dto->specs)) {
            $html .= "<div class=\"buying-guide-specs\">";
            $html .= "<h4>Specifications</h4>";
            $html .= "<dl class=\"specs-list\">";
            foreach ($dto->specs as $spec) {
                $html .= "<dt>" . $this->escape($spec['text']) . "</dt>";
                $html .= "<dd>" . $this->escape($spec['value']) . "</dd>";
            }
            $html .= "</dl>";
            $html .= "</div>";
        }

        if ($dto->showReviewPanel && (!empty($dto->pros) || !empty($dto->cons))) {
            $html .= "<div class=\"buying-guide-review\">";

            if (!empty($dto->pros)) {
                $html .= "<div class=\"guide-pros\">";
                $html .= "<h5>Advantages</h5>";
                $html .= "<ul>";
                foreach ($dto->pros as $pro) {
                    $html .= "<li>" . $this->escape($pro) . "</li>";
                }
                $html .= "</ul>";
                $html .= "</div>";
            }

            if (!empty($dto->cons)) {
                $html .= "<div class=\"guide-cons\">";
                $html .= "<h5>Considerations</h5>";
                $html .= "<ul>";
                foreach ($dto->cons as $con) {
                    $html .= "<li>" . $this->escape($con) . "</li>";
                }
                $html .= "</ul>";
                $html .= "</div>";
            }

            $html .= "</div>";
        }

        if (!empty($dto->url)) {
            $linkAttrs = '';
            if ($dto->noFollow) $linkAttrs .= ' rel="nofollow"';
            if ($dto->sponsored) $linkAttrs .= ' rel="sponsored"';
            if ($dto->openInNewTab) $linkAttrs .= ' target="_blank"';

            $html .= "<a href=\"{$dto->url}\"{$linkAttrs} class=\"buying-guide-button\">";
            $html .= $this->escape($dto->linkText);
            $html .= "</a>";
        }

        $html .= "</div>";

        return $html;
    }

    protected function getSupportedType(): string
    {
        return 'buying-guide';
    }
}