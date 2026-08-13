<?php

namespace App\Services\PublicContent\Images\Transform;

use InvalidArgumentException;

/**
 * Builds canonical CDN-style transform URLs for recognised image hosts and
 * supported file types: crop, resize, format and quality.
 *
 * Width/quality/crop already encoded in the source URL are respected when
 * the caller does not explicitly override them, and requested widths are
 * never allowed to exceed the known original width (no upscaling).
 */
final class RecognisedImageHostTransformer implements ImageTransformerInterface, ImageTransformCapabilities
{
    /**
     * @param list<string> $recognisedHosts Hosts (or parent domains, matched by suffix) this
     *                                       transformer is allowed to rewrite URLs for.
     */
    public function __construct(
        private readonly array $recognisedHosts,
        private readonly ImageUrlParameterReader $reader,
        private readonly ImageUrlStyleChooser $styleChooser,
        private readonly SimpleImageUrlBuilder $simpleBuilder,
        private readonly RichImageUrlBuilder $richBuilder,
        private readonly ?ImageBaseUrl $baseUrl = null,
    ) {
    }

    public function canCrop(): bool
    {
        return true;
    }

    public function canResize(): bool
    {
        return true;
    }

    public function canChangeFormat(): bool
    {
        return true;
    }

    public function canChangeQuality(): bool
    {
        return true;
    }

    public function baseUrl(): ?ImageBaseUrl
    {
        return $this->baseUrl;
    }

    public function supports(string $url): bool
    {
        return $this->isRecognisedHost($url) && $this->hasSupportedExtension($url);
    }

    public function transform(string $url, ImageTransformOptions $options): string
    {
        if (!$this->supports($url)) {
            throw new InvalidArgumentException('Image host or file type is not eligible for transformation.');
        }

        $existing = $this->reader->read($url);
        $effective = $this->effectiveOptions($existing, $options);
        $style = $this->styleChooser->choose($effective->hasCrop(), $existing->isRich());

        return match ($style) {
            ImageUrlStyle::Rich => $this->richBuilder->build($existing->baseUrl, $effective),
            ImageUrlStyle::Simple => $this->simpleBuilder->build($existing->baseUrl, $effective),
        };
    }

    /**
     * Merges caller-requested options over whatever was already encoded in
     * the URL, then enforces the no-upscale rule.
     */
    private function effectiveOptions(ImageUrlParameters $existing, ImageTransformOptions $requested): ImageTransformOptions
    {
        $width = $requested->width ?? $existing->width;

        if ($width !== null && $requested->originalWidth !== null) {
            $width = min($width, $requested->originalWidth);
        }

        return new ImageTransformOptions(
            width: $width,
            quality: $requested->quality ?? $existing->quality,
            format: $requested->format,
            crop: $requested->crop ?? $existing->crop,
            originalWidth: $requested->originalWidth,
        );
    }

    private function isRecognisedHost(string $url): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));

        if ($host === '') {
            return false;
        }

        foreach ($this->recognisedHosts as $recognisedHost) {
            $recognisedHost = strtolower($recognisedHost);

            if ($host === $recognisedHost || str_ends_with($host, '.' . $recognisedHost)) {
                return true;
            }
        }

        return false;
    }

    private function hasSupportedExtension(string $url): bool
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return SupportedImageFormat::tryFrom($extension) !== null;
    }
}
