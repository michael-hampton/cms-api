<?php

namespace App\Tests\Unit\Services\PublicContent\Images;

use App\Framework\Support\Cache\Cache;
use App\Services\PublicContent\Images\PublicContentImageAssetResolver;
use App\Services\PublicContent\Images\PublicContentImageUrlSigner;
use PHPUnit\Framework\TestCase;

final class PublicContentImageAssetResolverTest extends TestCase
{
    private string $basePath;
    private string $filePath;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->basePath = rtrim((string) config('upload.path', 'uploads'), '/');
        $this->filePath = $this->basePath . '/images/public-content-test.png';

        if (!is_dir(dirname($this->filePath))) {
            mkdir(dirname($this->filePath), 0775, true);
        }

        file_put_contents($this->filePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
    }

    protected function tearDown(): void
    {
        if (is_file($this->filePath)) {
            unlink($this->filePath);
        }

        Cache::flush();
        parent::tearDown();
    }

    public function test_resolves_signed_storage_upload_path_to_cached_asset(): void
    {
        $signer = new PublicContentImageUrlSigner();
        $resolver = new PublicContentImageAssetResolver($signer);

        $asset = $resolver->resolve($signer->sign('/storage/uploads/images/public-content-test.png'));

        self::assertNotNull($asset);
        self::assertSame('/storage/uploads/images/public-content-test.png', $asset->path);
        self::assertSame('image/png', $asset->mimeType);
        self::assertSame(filesize($this->filePath), $asset->size);
        self::assertNotSame('', $asset->etag);
    }

    public function test_rejects_invalid_or_unsafe_tokens(): void
    {
        $resolver = new PublicContentImageAssetResolver(new PublicContentImageUrlSigner());

        self::assertNull($resolver->resolve('not-a-valid-token'));
    }
}
