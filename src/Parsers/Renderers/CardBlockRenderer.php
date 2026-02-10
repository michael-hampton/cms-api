<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\CardBlockDto;

class CardBlockRenderer extends BaseBlockRenderer
{
    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof CardBlockDto) {
            throw new \InvalidArgumentException('Expected CardBlockDto');
        }

        $containerClass = "card-block card-block-{$dto->context} card-items-{$dto->itemsPerRow} card-layout-{$dto->layout} card-align-{$dto->alignment}";

        $html = "<div class=\"{$containerClass}\">";
        $html .= "<div class=\"card-container\">";

        // Image Section
        if (!empty($dto->image)) {
            $html .= $this->renderImageSection($dto);
        }

        // Content Section
        $html .= "<div class=\"card-content\">";

        // Sponsor Declaration (Top)
        if (!empty($dto->sponsorDeclaration)) {
            $html .= $this->renderSponsorDeclaration($dto->sponsorDeclaration);
        }

        // Title
        if (!empty($dto->title)) {
            $html .= "<h3 class=\"card-title\">" . $this->escape($dto->title) . "</h3>";
        }

        // Description
        if (!empty($dto->description)) {
            $html .= "<div class=\"card-description\">" . $this->escape($dto->description) . "</div>";
        }

        // Button/Link
        if (!empty($dto->linkUrl)) {
            $html .= $this->renderButton($dto);
        }

        $html .= "</div>"; // card-content
        $html .= "</div>"; // card-container
        $html .= "</div>"; // card-block

        return $html;
    }

    private function renderImageSection(CardBlockDto $dto): string
    {
        $html = "<div class=\"card-image-wrapper\">";

        if (!empty($dto->linkUrl)) {
            $html .= "<a href=\"{$dto->linkUrl}\"" . $this->getLinkAttributes($dto) . ">";
        }

        $html .= "<div class=\"card-image\">";
        $html .= "<img src=\"{$dto->image['src']}\" ";
        $html .= "alt=\"" . $this->escape($dto->image['alt'] ?: $dto->title) . "\" ";
        $html .= "loading=\"lazy\">";

        // Endorsement Badge
        if (!empty($dto->endorsement)) {
            $html .= "<div class=\"card-endorsement\">";
            $html .= "<img src=\"{$dto->endorsement['src']}\" ";
            $html .= "alt=\"" . $this->escape($dto->endorsement['alt']) . "\" ";
            $html .= "loading=\"lazy\">";
            $html .= "</div>";
        }

        $html .= "</div>"; // card-image

        if (!empty($dto->linkUrl)) {
            $html .= "</a>";
        }

        $html .= "</div>"; // card-image-wrapper

        return $html;
    }

    private function getLinkAttributes(CardBlockDto $dto): string
    {
        $attributes = [];

        if ($dto->openInNewTab) {
            $attributes[] = 'target="_blank"';
            $attributes[] = 'rel="noopener noreferrer"';
        }

        $rel = [];
        if ($dto->noFollow) {
            $rel[] = 'nofollow';
        }
        if ($dto->sponsored) {
            $rel[] = 'sponsored';
        }

        if (!empty($rel)) {
            $relString = implode(' ', $rel);
            if ($dto->openInNewTab) {
                // Merge with existing noopener noreferrer
                $existingRel = array_filter($attributes, fn($attr) => str_starts_with($attr, 'rel='));
                if ($existingRel) {
                    $attributes = array_filter($attributes, fn($attr) => !str_starts_with($attr, 'rel='));
                }
                $attributes[] = 'rel="noopener noreferrer ' . $relString . '"';
            } else {
                $attributes[] = 'rel="' . $relString . '"';
            }
        }

        return !empty($attributes) ? ' ' . implode(' ', array_unique($attributes)) : '';
    }

    private function renderSponsorDeclaration(array $declaration): string
    {
        $html = "<div class=\"card-sponsor-declaration\">";

        if (!empty($declaration['sponsorLogo'])) {
            $html .= "<div class=\"sponsor-logo\">";
            $html .= "<img src=\"{$declaration['sponsorLogo']['src']}\" ";
            $html .= "alt=\"" . $this->escape($declaration['sponsorLogo']['alt']) . "\" ";
            $html .= "loading=\"lazy\">";
            $html .= "</div>";
        }

        $html .= "<div class=\"sponsor-text\">";

        if (!empty($declaration['sponsoredText'])) {
            $html .= "<span class=\"sponsored-label\">" . $this->escape($declaration['sponsoredText']) . "</span>";
        }

        if (!empty($declaration['sponsorName'])) {
            $html .= "<span class=\"sponsor-name\">" . $this->escape($declaration['sponsorName']) . "</span>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    private function renderButton(CardBlockDto $dto): string
    {
        $class = "card-button card-button-{$dto->buttonType}";
        $attributes = $this->getLinkAttributes($dto);

        return "<a href=\"{$dto->linkUrl}\" class=\"{$class}\"{$attributes}>" . $this->escape($dto->buttonText) . "</a>";
    }

    protected function getSupportedType(): string
    {
        return 'card';
    }
}