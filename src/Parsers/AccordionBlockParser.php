<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;

class AccordionBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'accordion';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [new MaxLengthRule(255)],
            'items' => [new RequiredRule(), new ArrayRule()],
            'allowMultipleOpen' => [new BooleanRule()],
            'openFirstByDefault' => [new BooleanRule()]
        ];
    }

    public function parse(array $data): array
    {
        $title = trim($data['title'] ?? '');
        $items = $data['items'] ?? [];
        $allowMultipleOpen = (bool)($data['allowMultipleOpen'] ?? false);
        $openFirstByDefault = (bool)($data['openFirstByDefault'] ?? true);

        // Validate items
        $validatedItems = [];
        foreach ($items as $index => $item) {
            if (empty($item['question']) || empty($item['answer'])) {
                continue; // Skip invalid items
            }

            $validatedItems[] = [
                'question' => trim($item['question']),
                'answer' => trim($item['answer']),
                'isOpen' => $index === 0 && $openFirstByDefault ? true : (bool)($item['isOpen'] ?? false)
            ];
        }

        return [
            'title' => $title,
            'items' => $validatedItems,
            'allowMultipleOpen' => $allowMultipleOpen,
            'openFirstByDefault' => $openFirstByDefault,
            'context' => $data['context'] ?? 'default',
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
        $html = "<div class=\"accordion-block accordion-sidebar\">";

        if (!empty($parsedData['title'])) {
            $html .= "<h4 class=\"accordion-title-sidebar\">{$parsedData['title']}</h4>";
        }

        $html .= "<div class=\"accordion-container-sidebar\" data-allow-multiple=\"" . ($parsedData['allowMultipleOpen'] ? 'true' : 'false') . "\">";

        foreach ($parsedData['items'] as $index => $item) {
            $questionText = htmlspecialchars($item['question']);
            $answerText = nl2br(htmlspecialchars($item['answer']));
            $isOpen = $item['isOpen'] ? 'true' : 'false';
            $displayStyle = $item['isOpen'] ? 'block' : 'none';
            $accordionId = 'accordion-sidebar-item-' . $index;

            $html .= "<div class=\"accordion-item-sidebar\" data-accordion-item>";
            $html .= "<button class=\"accordion-header-sidebar\" aria-expanded=\"{$isOpen}\" aria-controls=\"{$accordionId}\">";
            $html .= "<span class=\"accordion-question-sidebar\">{$questionText}</span>";
            $html .= "<span class=\"accordion-icon-sidebar\">▼</span>";
            $html .= "</button>";
            $html .= "<div class=\"accordion-content-sidebar\" id=\"{$accordionId}\" style=\"display: {$displayStyle};\">";
            $html .= "<div class=\"accordion-answer-sidebar\">{$answerText}</div>";
            $html .= "</div>";
            $html .= "</div>";
        }

        $html .= "</div>";
        $html .= "</div>";

        // Add JavaScript
        $html .= $this->generateAccordionScript();

        return $html;
    }

    private function generateAccordionScript(): string
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
                        
                        // If not allowing multiple, close all others first
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
                        
                        // Toggle current item
                        this.setAttribute('aria-expanded', !isExpanded);
                        content.style.display = isExpanded ? 'none' : 'block';
                    });
                });
            });
        })();
        </script>";
    }

    private function generateDefaultHtml(array $parsedData): string
    {
        $html = "<div class=\"accordion-block\">";

        if (!empty($parsedData['title'])) {
            $html .= "<h3 class=\"accordion-title\">{$parsedData['title']}</h3>";
        }

        $html .= "<div class=\"accordion-container\" data-allow-multiple=\"" . ($parsedData['allowMultipleOpen'] ? 'true' : 'false') . "\">";

        foreach ($parsedData['items'] as $index => $item) {
            $questionText = htmlspecialchars($item['question']);
            $answerText = nl2br(htmlspecialchars($item['answer']));
            $isOpen = $item['isOpen'] ? 'true' : 'false';
            $displayStyle = $item['isOpen'] ? 'block' : 'none';
            $accordionId = 'accordion-item-' . $index;

            $html .= "<div class=\"accordion-item\" data-accordion-item>";
            $html .= "<button class=\"accordion-header\" aria-expanded=\"{$isOpen}\" aria-controls=\"{$accordionId}\">";
            $html .= "<span class=\"accordion-question\">{$questionText}</span>";
            $html .= "<span class=\"accordion-icon\">▼</span>";
            $html .= "</button>";
            $html .= "<div class=\"accordion-content\" id=\"{$accordionId}\" style=\"display: {$displayStyle};\">";
            $html .= "<div class=\"accordion-answer\">{$answerText}</div>";
            $html .= "</div>";
            $html .= "</div>";
        }

        $html .= "</div>";
        $html .= "</div>";

        // Add JavaScript for accordion functionality
        $html .= $this->generateAccordionScript();

        return $html;
    }
}