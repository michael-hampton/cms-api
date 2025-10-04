<?php

// VideoBlockParser.php
namespace App\Parsers;

use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\UrlRule;

class VideoBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'video';
    }

    public function getValidationRules(): array
    {
        return [
            'url' => [
                new RequiredRule(),
                new UrlRule(),
            ],
            'preview' => [
                new MaxLengthRule(500)
            ],
            'caption' => [
                new MaxLengthRule(500)
            ]
        ];
    }

    public function parse(array $data): array
    {
        $url = $data['url'] ?? '';
        $caption = trim($data['caption'] ?? '');

        return [
            'url' => $url,
            'preview' => $this->generatePreview($url),
            'caption' => $caption,
            'platform' => $this->detectPlatform($url),
            'video_id' => $this->extractVideoId($url),
            'embed_url' => $this->generateEmbedUrl($url),
            'word_count' => str_word_count($caption),
            'formatted_caption' => nl2br(htmlspecialchars($caption)),
            'has_caption' => !empty($caption)
        ];
    }

    private function detectPlatform(string $url): string
    {
        if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
            return 'youtube';
        }
        if (strpos($url, 'vimeo.com') !== false) {
            return 'vimeo';
        }
        return 'unknown';
    }

    private function extractVideoId(string $url): ?string
    {
        if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
            return $this->extractYouTubeVideoId($url);
        }
        if (strpos($url, 'vimeo.com') !== false) {
            return $this->extractVimeoVideoId($url);
        }
        return null;
    }

    private function extractYouTubeVideoId(string $url): ?string
    {
        $regex = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/';
        preg_match($regex, $url, $matches);
        return $matches[1] ?? null;
    }

    private function extractVimeoVideoId(string $url): ?string
    {
        $regex = '/(?:vimeo\.com\/)(\d+)/';
        preg_match($regex, $url, $matches);
        return $matches[1] ?? null;
    }

    private function generateEmbedUrl(string $url): ?string
    {
        $videoId = $this->extractVideoId($url);
        if (!$videoId) {
            return null;
        }

        $platform = $this->detectPlatform($url);
        if ($platform === 'youtube') {
            return "https://www.youtube.com/embed/{$videoId}";
        }
        if ($platform === 'vimeo') {
            return "https://player.vimeo.com/video/{$videoId}";
        }
        return null;
    }

    private function generatePreview(string $url): string
    {
        $embedUrl = $this->generateEmbedUrl($url);
        return $embedUrl ?: "Video Preview: {$url}";
    }

    public function generateHtml(array $parsedData): string
    {
        $html = "<div class=\"video-block video-platform-{$parsedData['platform']}\">";

        if (!empty($parsedData['embed_url'])) {
            $html .= "<div class=\"video-container\">";
            $html .= "<iframe src=\"{$parsedData['embed_url']}\" class=\"video-iframe\" frameborder=\"0\" allowfullscreen></iframe>";
            $html .= "</div>";
        } else {
            $html .= "<div class=\"video-fallback\">";
            $html .= "<a href=\"{$parsedData['url']}\" class=\"video-link\" target=\"_blank\" rel=\"noopener noreferrer\">";
            $html .= "Watch Video";
            $html .= "</a>";
            $html .= "</div>";
        }

        if ($parsedData['has_caption']) {
            $html .= "<div class=\"video-caption\">{$parsedData['formatted_caption']}</div>";
        }

        $html .= "</div>";

        return $html;
    }
}