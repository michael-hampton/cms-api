<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\AccordionBlockDto;
use App\Parsers\Dtos\BlockDtoInterface;

class AccordionBlockRenderer extends BaseBlockRenderer
{
    protected function getSupportedType(): string
    {
        return 'accordion';
    }

    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof AccordionBlockDto) {
            throw new \InvalidArgumentException('Expected AccordionBlockDto');
        }

        if ($dto->context === 'sidebar') {
            return $this->renderSidebar($dto);
        }

        return $this->renderDefault($dto);
    }

    private function renderSidebar(AccordionBlockDto $dto): string
    {
        $html = "<div class=\"accordion-block accordion-sidebar accordion-theme-{$dto->theme}\">";

        if (!empty($dto->title)) {
            $escapedTitle = $this->escape($dto->title);
            $html .= "<h4 class=\"accordion-title-sidebar\">{$escapedTitle}</h4>";
        }

        if (!empty($dto->introContent)) {
            $html .= "<div class=\"accordion-intro-sidebar\">" . $this->escapeWithBreaks($dto->introContent) . "</div>";
        }

        $html .= $this->renderItems($dto, 'sidebar');
        $html .= "</div>";
        $html .= $this->renderScript();

        return $html;
    }

    private function renderDefault(AccordionBlockDto $dto): string
    {
        $html = "<div class=\"accordion-block accordion-theme-{$dto->theme}\">";

        if (!empty($dto->title)) {
            $escapedTitle = $this->escape($dto->title);
            $html .= "<h3 class=\"accordion-title\">{$escapedTitle}</h3>";
        }

        if (!empty($dto->introContent)) {
            $html .= "<div class=\"accordion-intro\">" . $this->escapeWithBreaks($dto->introContent) . "</div>";
        }

        $html .= $this->renderItems($dto, 'default');
        $html .= "</div>";
        $html .= $this->renderScript();

        return $html;
    }

    private function renderItems(AccordionBlockDto $dto, string $context): string
    {
        $suffix = $context === 'sidebar' ? '-sidebar' : '';
        $allowMultiple = $dto->allowMultipleOpen ? 'true' : 'false';

        $html = "<div class=\"accordion-container{$suffix}\" data-allow-multiple=\"{$allowMultiple}\" data-visible-count=\"{$dto->visibleItemsCount}\">";

        $accordionId = uniqid('accordion-');

        foreach ($dto->items as $index => $item) {
            $itemClass = $index >= $dto->visibleItemsCount ? ' accordion-item-hidden' : '';
            $questionText = $this->escape($item['question']);
            $answerText = $this->escapeWithBreaks($item['answer']);
            $isOpen = $item['isOpen'] ? 'true' : 'false';
            $displayStyle = $item['isOpen'] ? 'block' : 'none';
            $itemId = "{$accordionId}-item-{$index}";

            $html .= "<div class=\"accordion-item{$suffix}{$itemClass}\" data-accordion-item data-item-index=\"{$index}\">";
            $html .= "<button class=\"accordion-header{$suffix}\" aria-expanded=\"{$isOpen}\" aria-controls=\"{$itemId}\">";
            $html .= "<span class=\"accordion-question{$suffix}\">{$questionText}</span>";
            $html .= "<span class=\"accordion-icon{$suffix}\">▼</span>";
            $html .= "</button>";
            $html .= "<div class=\"accordion-content{$suffix}\" id=\"{$itemId}\" style=\"display: {$displayStyle};\">";
            $html .= "<div class=\"accordion-answer{$suffix}\">{$answerText}</div>";
            $html .= "</div>";
            $html .= "</div>";
        }

        $html .= "</div>";

        if (count($dto->items) > $dto->visibleItemsCount) {
            $html .= "<div class=\"accordion-load-more-container{$suffix}\">";
            $html .= "<button class=\"accordion-load-more-btn{$suffix}\" data-accordion-id=\"{$accordionId}\">Load More Questions</button>";
            $html .= "</div>";
        }

        return $html;
    }

    private function renderScript(): string
    {
        return "<script>
        (function() {
            const accordionContainers = document.querySelectorAll('.accordion-container, .accordion-container-sidebar');
            
            accordionContainers.forEach(container => {
                const allowMultiple = container.getAttribute('data-allow-multiple') === 'true';
                const headers = container.querySelectorAll('[class^=\"accordion-header\"]');
                
                headers.forEach(header => {
                    header.addEventListener('click', function() {
                        const isExpanded = this.getAttribute('aria-expanded') === 'true';
                        const content = this.nextElementSibling;
                        const item = this.closest('[data-accordion-item]');
                        
                        if (!allowMultiple && !isExpanded) {
                            const allItems = container.querySelectorAll('[data-accordion-item]');
                            allItems.forEach(otherItem => {
                                if (otherItem !== item) {
                                    const otherHeader = otherItem.querySelector('[class^=\"accordion-header\"]');
                                    const otherContent = otherItem.querySelector('[class^=\"accordion-content\"]');
                                    if (otherHeader && otherContent) {
                                        otherHeader.setAttribute('aria-expanded', 'false');
                                        otherContent.style.display = 'none';
                                    }
                                }
                            });
                        }
                        
                        this.setAttribute('aria-expanded', !isExpanded);
                        content.style.display = isExpanded ? 'none' : 'block';
                    });
                });
            });

            const loadMoreButtons = document.querySelectorAll('[class*=\"accordion-load-more-btn\"]');
            
            loadMoreButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const container = this.closest('.accordion-block').querySelector('[class*=\"accordion-container\"]');
                    const hiddenItems = container.querySelectorAll('.accordion-item-hidden');
                    const visibleCount = parseInt(container.getAttribute('data-visible-count')) || 5;
                    
                    let shown = 0;
                    hiddenItems.forEach(item => {
                        if (shown < visibleCount) {
                            item.classList.remove('accordion-item-hidden');
                            shown++;
                        }
                    });
                    
                    const remainingHidden = container.querySelectorAll('.accordion-item-hidden');
                    if (remainingHidden.length === 0) {
                        this.parentElement.style.display = 'none';
                    }
                });
            });
        })();
        </script>";
    }
}