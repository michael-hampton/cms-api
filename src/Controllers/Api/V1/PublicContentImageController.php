<?php

namespace App\Controllers\Api\V1;

use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Services\PublicContent\Images\PublicContentImageAssetResolver;
use RuntimeException;

final class PublicContentImageController extends Controller
{
    private const string FALLBACK_IMAGE = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="675" viewBox="0 0 1200 675" role="img" aria-label="Image unavailable">
    <rect width="1200" height="675" fill="#f3f4f6"/>
    <g fill="none" stroke="#9ca3af" stroke-width="24" stroke-linecap="round" stroke-linejoin="round">
        <rect x="390" y="180" width="420" height="315" rx="24"/>
        <circle cx="510" cy="285" r="42"/>
        <path d="M420 450l120-120 90 90 60-60 90 90"/>
    </g>
    <text x="600" y="565" text-anchor="middle" font-family="Arial, sans-serif" font-size="36" fill="#6b7280">Image unavailable</text>
</svg>
SVG;

    public function __construct(private readonly PublicContentImageAssetResolver $images)
    {
        parent::__construct();
    }

    public function show(string $token, Request $request): Response
    {
        try {
            $asset = $this->images->resolve($token);
        } catch (RuntimeException) {
            return new Response('Unsupported image.', 415, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        if ($asset === null) {
            return $this->fallbackImage();
        }

        $ifNoneMatch = $request->header('If-None-Match');
        if ($ifNoneMatch !== null && trim($ifNoneMatch) === $asset->etag) {
            return new Response('', 304, $this->headers($asset, false));
        }

        return new Response($asset->content, 200, $this->headers($asset));
    }

    private function fallbackImage(): Response
    {
        return new Response(self::FALLBACK_IMAGE, 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Content-Length' => (string) strlen(self::FALLBACK_IMAGE),
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'X-Image-Fallback' => 'true',
        ]);
    }

    private function headers($asset, bool $includeBodyHeaders = true): array
    {
        $headers = [
            'Content-Type' => $asset->mimeType,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $asset->etag,
            'Last-Modified' => gmdate('D, d M Y H:i:s', $asset->lastModified) . ' GMT',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($includeBodyHeaders) {
            $headers['Content-Length'] = (string) $asset->size;
        }

        return $headers;
    }
}
