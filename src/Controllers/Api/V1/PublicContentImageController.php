<?php

namespace App\Controllers\Api\V1;

use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Services\PublicContent\Images\PublicContentImageAssetResolver;
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
            return new Response('Image not found.', 404, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        $ifNoneMatch = $request->header('If-None-Match');
        if ($ifNoneMatch !== null && trim($ifNoneMatch) === $asset->etag) {
            return new Response('', 304, $this->headers($asset, false));
        }

        return new Response($asset->content, 200, $this->headers($asset));
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
