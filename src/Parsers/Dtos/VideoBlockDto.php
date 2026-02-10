<?php

namespace App\Parsers\Dtos;

use InvalidArgumentException;

final class VideoBlockDto extends BaseBlockDto
{
    private const KNOWN_KEYS = [
        'url', 'preview', 'caption', 'platform', 'video_id', 'embed_url'
    ];

    private const MAX_CAPTION_LENGTH = 500;

    public function __construct(
        public string  $url,
        public string $preview,
        public string  $caption,
        public string  $platform,
        public ?string $videoId,
        public ?string $embedUrl
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'caption' => ''
        ]);

        if (empty($data['url'])) {
            throw new InvalidArgumentException('Video URL is required');
        }

        $url = trim($data['url']);
        $caption = trim($data['caption']);

        if (strlen($caption) > self::MAX_CAPTION_LENGTH) {
            if (self::$debugMode) {
                error_log("WARNING: Caption exceeds max length, truncating");
            }
            $caption = substr($caption, 0, self::MAX_CAPTION_LENGTH);
        }

        $platform = self::detectPlatform($url);
        $videoId = self::extractVideoId($url, $platform);
        $embedUrl = self::generateEmbedUrl($videoId, $platform);

        return new self($url, $data['preview'] ?? '', $caption, $platform, $videoId, $embedUrl);
    }

    private static function detectPlatform(string $url): string
    {
        if (stripos($url, 'youtube.com') !== false || stripos($url, 'youtu.be') !== false) {
            return 'youtube';
        }
        if (stripos($url, 'vimeo.com') !== false) {
            return 'vimeo';
        }
        return 'unknown';
    }

    private static function extractVideoId(string $url, string $platform): ?string
    {
        if ($platform === 'youtube') {
            preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches);
            return $matches[1] ?? null;
        }

        if ($platform === 'vimeo') {
            preg_match('/(?:vimeo\.com\/)(\d+)/', $url, $matches);
            return $matches[1] ?? null;
        }

        return null;
    }

    private static function generateEmbedUrl(?string $videoId, string $platform): ?string
    {
        if (!$videoId) {
            return null;
        }

        return match ($platform) {
            'youtube' => "https://www.youtube.com/embed/{$videoId}",
            'vimeo' => "https://player.vimeo.com/video/{$videoId}",
            default => null
        };
    }

    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'caption' => $this->caption,
            'platform' => $this->platform,
            'video_id' => $this->videoId,
            'embed_url' => $this->embedUrl,
            'has_caption' => !empty($this->caption),
            'preview' => $this->preview,
        ];
    }

    public function getType(): string
    {
        return 'video';
    }
}