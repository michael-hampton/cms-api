<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\InRule;
use App\Framework\Validation\Rules\IntegerRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\MaxRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\RequiredRule;

class AccordionBlockParser extends BaseBlockParser
{
    private const ALLOWED_THEMES = ['light', 'dark', 'colored', 'minimal'];
    private const ALLOWED_CONTEXTS = ['default', 'sidebar'];
    private const MIN_VISIBLE_ITEMS = 1;
    private const MAX_VISIBLE_ITEMS = 50;
    private const MAX_INTRO_LENGTH = 5000;

    public function getType(): string
    {
        return 'accordion';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [new MaxLengthRule(255)],
            'introContent' => [new MaxLengthRule(self::MAX_INTRO_LENGTH)],
            'items' => [new RequiredRule(), new ArrayRule()],
            'allowMultipleOpen' => [new BooleanRule()],
            'openFirstByDefault' => [new BooleanRule()],
            'context' => [new InRule(self::ALLOWED_CONTEXTS)],
            'theme' => [new InRule(self::ALLOWED_THEMES)],
            'visibleItemsCount' => [
                new IntegerRule(),
                new MinRule(self::MIN_VISIBLE_ITEMS),
                new MaxRule(self::MAX_VISIBLE_ITEMS)
            ]
        ];
    }

    public function parse(array $data): array
    {
        $title = trim($data['title'] ?? '');
        $introContent = trim($data['introContent'] ?? '');
        $items = $data['items'] ?? [];
        $allowMultipleOpen = (bool)($data['allowMultipleOpen'] ?? false);
        $openFirstByDefault = (bool)($data['openFirstByDefault'] ?? true);
        $context = $data['context'] ?? 'default';
        $theme = $data['theme'] ?? 'light';
        $visibleItemsCount = (int)($data['visibleItemsCount'] ?? 5);

        // Validate theme
        if (!in_array($theme, self::ALLOWED_THEMES, true)) {
            $theme = 'light';
        }

        // Validate context
        if (!in_array($context, self::ALLOWED_CONTEXTS, true)) {
            $context = 'default';
        }

        // Validate visible items count
        $visibleItemsCount = max(self::MIN_VISIBLE_ITEMS, min(self::MAX_VISIBLE_ITEMS, $visibleItemsCount));

        // Validate and sort items
        $validatedItems = [];
        foreach ($items as $index => $item) {
            if (empty($item['question']) || empty($item['answer'])) {
                continue;
            }

            $order = isset($item['order']) ? (int)$item['order'] : $index;

            $validatedItems[] = [
                'question' => trim($item['question']),
                'answer' => trim($item['answer']),
                'isOpen' => $index === 0 && $openFirstByDefault ? true : (bool)($item['isOpen'] ?? false),
                'order' => $order
            ];
        }

        // Sort items by order
        usort($validatedItems, function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        // Reindex orders
        foreach ($validatedItems as $index => &$item) {
            $item['order'] = $index;
        }

        return [
            'title' => $title,
            'introContent' => $introContent,
            'items' => $validatedItems,
            'allowMultipleOpen' => $allowMultipleOpen,
            'openFirstByDefault' => $openFirstByDefault,
            'context' => $context,
            'theme' => $theme,
            'visibleItemsCount' => $visibleItemsCount,
            'total_items' => count($validatedItems)
        ];
    }

    public function generateHtml(array $parsedData): string
    {
        $context = $parsedData['context'] ?? 'default';

        if ($context === 'sidebar') {
            return $this->generateSidebarHtml($parsedData);
        }

        return $this->generateDefaultHtml($parsedData);
    }

    private function generateSidebarHtml(array $parsedData): string
    {
        $theme = $parsedData['theme'] ?? 'light';
        $html = "<div class=\"accordion-block accordion-sidebar accordion-theme-{$theme}\">";

        if (!empty($parsedData['title'])) {
            $escapedTitle = htmlspecialchars($parsedData['title']);
            $html .= "<h4 class=\"accordion-title-sidebar\">{$escapedTitle}</h4>";
        }

        if (!empty($parsedData['introContent'])) {
            $html .= "<div class=\"accordion-intro-sidebar\">" . nl2br(htmlspecialchars($parsedData['introContent'])) . "</div>";
        }

        $html .= $this->generateAccordionItems($parsedData, 'sidebar');
        $html .= "</div>";
        $html .= $this->generateAccordionScript();

        return $html;
    }

    private function generateAccordionScript(): string
    {
        return "<script>
        (function() {
            // Accordion toggle functionality
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

            // Load More functionality
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

    private function generateDefaultHtml(array $parsedData): string
    {
        $theme = $parsedData['theme'] ?? 'light';
        $html = "<div class=\"accordion-block accordion-theme-{$theme}\">";

        if (!empty($parsedData['title'])) {
            $escapedTitle = htmlspecialchars($parsedData['title']);
            $html .= "<h3 class=\"accordion-title\">{$escapedTitle}</h3>";
        }

        if (!empty($parsedData['introContent'])) {
            $html .= "<div class=\"accordion-intro\">" . nl2br(htmlspecialchars($parsedData['introContent'])) . "</div>";
        }

        $html .= $this->generateAccordionItems($parsedData, 'default');
        $html .= "</div>";
        $html .= $this->generateAccordionScript();

        return $html;
    }

    private function generateAccordionItems(array $parsedData, string $context): string
    {
        $items = $parsedData['items'];
        $visibleCount = $parsedData['visibleItemsCount'];
        $allowMultiple = $parsedData['allowMultipleOpen'];
        $suffix = $context === 'sidebar' ? '-sidebar' : '';

        $html = "<div class=\"accordion-container{$suffix}\" data-allow-multiple=\"" . ($allowMultiple ? 'true' : 'false') . "\" data-visible-count=\"{$visibleCount}\">";

        $accordionId = uniqid('accordion-');

        foreach ($items as $index => $item) {
            $itemClass = $index >= $visibleCount ? ' accordion-item-hidden' : '';
            $questionText = htmlspecialchars($item['question']);
            $answerText = nl2br(htmlspecialchars($item['answer']));
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

        // Add "Load More" button if needed
        if (count($items) > $visibleCount) {
            $html .= "<div class=\"accordion-load-more-container{$suffix}\">";
            $html .= "<button class=\"accordion-load-more-btn{$suffix}\" data-accordion-id=\"{$accordionId}\">Load More Questions</button>";
            $html .= "</div>";
        }

        return $html;
    }
}