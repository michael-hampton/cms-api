<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\VideoBlockDto;

class VideoBlockRenderer extends BaseBlockRenderer
{
    protected function getSupportedType(): string
    {
        return 'video';
    }

    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof VideoBlockDto) {
            return '';
        }

        $html = "<div class=\"video-block video-platform-{$dto->platform}\">";

        if ($dto->embedUrl) {
            $html .= "<div class=\"video-container\">";
            $html .= "<iframe src=\"{$this->escape($dto->embedUrl)}\" class=\"video-iframe\" frameborder=\"0\" allowfullscreen></iframe>";
            $html .= "</div>";
        } else {
            $html .= "<div class=\"video-fallback\">";
            $html .= "<a href=\"{$this->escape($dto->url)}\" class=\"video-link\" target=\"_blank\" rel=\"noopener noreferrer\">";
            $html .= "Watch Video";
            $html .= "</a>";
            $html .= "</div>";
        }

        if (!empty($dto->caption)) {
            $html .= "<div class=\"video-caption\">{$this->escapeWithBreaks($dto->caption)}</div>";
        }

        $html .= "</div>";

        return $html;
    }
}