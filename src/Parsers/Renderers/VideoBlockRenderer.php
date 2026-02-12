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

        $embedUrl = $dto->embedUrl;

        // Auto-generate embed URLs for known platforms
        if (!$embedUrl) {
            $platform = strtolower($dto->platform);
            $url = $dto->url;

            if ($platform === 'youtube' && preg_match('/[?&]v=([\w-]+)/', $url, $matches)) {
                $embedUrl = "https://www.youtube.com/embed/{$matches[1]}";
            }

            if ($platform === 'vimeo' && preg_match('#vimeo\.com/(?:.*?/)?(\d+)#', $url, $matches)) {
                $embedUrl = "https://player.vimeo.com/video/{$matches[1]}";
            }
        }

        // Render iframe if we have an embed URL
        if ($embedUrl) {
            $html .= "<div class=\"video-container\">";
            $html .= "<iframe src=\"{$this->escape($embedUrl)}\" class=\"video-iframe\" frameborder=\"0\" allowfullscreen></iframe>";
            $html .= "</div>";
        } else {
            // Fallback link for unknown platforms
            $html .= "<div class=\"video-fallback\">";
            $html .= "<a href=\"{$this->escape($dto->url)}\" class=\"video-link\" target=\"_blank\" rel=\"noopener noreferrer\">Watch Video</a>";
            $html .= "</div>";
        }

        if (!empty($dto->caption)) {
            $html .= "<div class=\"video-caption\">{$this->escapeWithBreaks($dto->caption)}</div>";
        }

        $html .= "</div>";

        return $html;
    }

}