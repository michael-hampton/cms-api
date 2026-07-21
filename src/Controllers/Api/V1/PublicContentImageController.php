<?php

namespace App\Controllers\Api\V1;

use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Services\PublicContent\Images\PublicContentImageAssetResolver;
use App\Services\PublicContent\Images\PublicContentMissingImageFallback;
use RuntimeException;

final class PublicContentImageController extends Controller
{
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
            return $this->fallback();
        }

        $ifNoneMatch = $request->header('If-None-Match');
        if ($ifNoneMatch !== null && trim($ifNoneMatch) === $asset->etag) {
            return new Response('', 304, $this->headers($asset, false));
        }

        return new Response($asset->content, 200, $this->headers($asset));
    }

    public function fallback(): Response
    {
        $path = dirname(__DIR__, 3) . PublicContentMissingImageFallback::ASSET_RELATIVE_PATH;
        $content = is_file($path) && is_readable($path)
            ? file_get_contents($path)
            : false;

        if ($content === false) {
            return new Response('Image not found.', 404, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        return new Response($content, 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Content-Length' => (string) strlen($content),
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
