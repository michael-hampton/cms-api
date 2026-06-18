<?php

namespace App\Controllers\Api\V1;

use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Services\PublicContent\Media\PublicMediaPathResolver;
use App\Services\PublicContent\Media\PublicMediaUrlSigner;

final class PublicMediaController extends Controller
{
    public function __construct(
        private readonly PublicMediaUrlSigner $signer,
        private readonly PublicMediaPathResolver $paths,
    ) {
        parent::__construct();
    }

    public function show(string $token, Request $request): Response
    {
        $siteSlug = (string) $request->route('site', '');
        $path = $this->signer->verify(
            $siteSlug,
            $token,
            $request->query('expires'),
            $request->query('signature'),
        );

        if ($path === null) {
            return Response::json(['error' => 'Invalid or expired media URL.'], 403);
        }

        $absolutePath = $this->paths->absolutePath($path);
        if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return Response::json(['error' => 'Media not found.'], 404);
        }

        $mimeType = $this->paths->mimeType($path) ?? 'application/octet-stream';
        $contents = file_get_contents($absolutePath);

        if ($contents === false) {
            return Response::json(['error' => 'Media not readable.'], 404);
        }

        $etag = '"' . sha1($path . '|' . filesize($absolutePath) . '|' . filemtime($absolutePath)) . '"';

        if ($request->header('If-None-Match') === $etag) {
            return new Response('', 304, [
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);
        }

        return new Response($contents, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
