<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\ImageBlockDto;

class ImageBlockRenderer extends BaseBlockRenderer
{
    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof ImageBlockDto) {
            return '';
        }

        $src = htmlspecialchars($dto->src, ENT_QUOTES, 'UTF-8');
        $alt = htmlspecialchars($dto->alt, ENT_QUOTES, 'UTF-8');
        $caption = $dto->caption;
        $credit = $dto->credit ?? '';
        $shouldDisplayCredit = $dto->shouldDisplayCredit ?? false;
        $layoutClass = 'image-layout-' . $dto->layout;
        $alignmentClass = 'image-align-' . $dto->alignment;
        $loading = $dto->context === 'sidebar' ? 'lazy' : 'eager';
        $priority = $loading === 'eager' ? ' fetchpriority="high"' : '';

        $contextClass = $dto->context === 'sidebar' ? ' image-sidebar' : '';
        $html = "<div class=\"image-block {$layoutClass} {$alignmentClass}{$contextClass}\">";

        if (!empty($dto->linkUrl)) {
            $linkUrl = htmlspecialchars($dto->linkUrl, ENT_QUOTES, 'UTF-8');

            $linkAttrs = $this->buildLinkAttributes($dto);

            $html .= "<a href=\"{$linkUrl}\"{$linkAttrs}>";
        }

        $html .= "<img src=\"{$src}\" alt=\"{$alt}\" loading=\"{$loading}\" decoding=\"async\"{$priority}>";

        if (!empty($dto->endorsements)) {
            foreach ($dto->endorsements as $position => $endorsement) {
                $endorsementSrc = htmlspecialchars($endorsement['url'], ENT_QUOTES, 'UTF-8');
                $endorsementAlt = htmlspecialchars($endorsement['alt'] ?? 'Endorsement', ENT_QUOTES, 'UTF-8');
                $html .= "<img src=\"{$endorsementSrc}\" alt=\"{$endorsementAlt}\" class=\"endorsement-image {$position}\" loading=\"lazy\" decoding=\"async\">";
            }
        }

        if (!empty($dto->linkUrl)) {
            $html .= "</a>";
        }

        if (!empty($caption)) {
            $html .= "<figcaption>{$caption}</figcaption>";
        }

        // Only display credit if required by image rights
        if ($shouldDisplayCredit && !empty($credit)) {
            $html .= "<div class=\"image-credit\"><small>📷 {$credit}</small></div>";
        }

        $html .= "</div>";

        return $html;
    }

    private function buildLinkAttributes(ImageBlockDto $dto): string
    {
        $attrs = [];

        if ($dto->openInNewTab) {
            $attrs[] = 'target="_blank"';
            $attrs[] = 'rel="noopener noreferrer"';
        }

        $relValues = [];
        if ($dto->noFollow) $relValues[] = 'nofollow';
        if ($dto->sponsored) $relValues[] = 'sponsored';

        if (!empty($relValues)) {
            $relString = implode(' ', $relValues);
            if ($dto->openInNewTab) {
                $attrs = array_filter($attrs, fn($attr) => !str_starts_with($attr, 'rel='));
                $attrs[] = 'rel="noopener noreferrer ' . $relString . '"';
            } else {
                $attrs[] = 'rel="' . $relString . '"';
            }
        }

        return !empty($attrs) ? ' ' . implode(' ', $attrs) : '';
    }

    protected function getSupportedType(): string
    {
        return 'image';
    }
}
