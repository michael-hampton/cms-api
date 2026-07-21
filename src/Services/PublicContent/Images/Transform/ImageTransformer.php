<?php

namespace App\Services\PublicContent\Images\Transform;

use Throwable;

/**
 * Facade for the image transform library.
 *
 * Fails open by design: an unrecognised host, or any failure while applying
 * a transform, is logged and the original URL is returned untouched. Callers
 * (e.g. {@see \App\Services\PublicContent\Images\PublicContentImageUrlTransformer})
 * can rely on this never throwing and never producing a broken image.
 */
final class ImageTransformer implements ImageTransformerInterface
{
    public function __construct(
        private readonly ImageTransformerInterface $recognisedTransformer,
        private readonly PassthroughImageTransformer $passthroughTransformer,
        private readonly ImageTransformLogger $logger,
    ) {
    }

    public function supports(string $url): bool
    {
        return $this->recognisedTransformer->supports($url);
    }

    public function transform(string $url, ImageTransformOptions $options): string
    {
        $trimmed = trim($url);

        if ($trimmed === '') {
            return $url;
        }

        if (!$this->recognisedTransformer->supports($trimmed)) {
            $this->logger->warning(
                'Image host is not recognised for transformation; serving original image untouched.',
                ['url' => $trimmed],
            );

            return $this->passthroughTransformer->transform($trimmed, $options);
        }

        try {
            return $this->recognisedTransformer->transform($trimmed, $options);
        } catch (Throwable $exception) {
            $this->logger->warning(
                'Image transform could not be applied; serving original image untouched.',
                [
                    'url' => $trimmed,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ],
            );

            return $this->passthroughTransformer->transform($trimmed, $options);
        }
    }
}
