<?php

namespace App\Services\Cms\Pages;

use App\Models\Image;

final class PageCardImageResolver
{
    /**
     * @return array{url: string, width: ?int, height: ?int}|null
     */
    public function resolve(object|array $page): ?array
    {
        $cropOverrides = $this->normaliseArray($this->value($page, 'crop_overrides'));
        $resolvedImages = $this->normaliseArray($this->value($page, 'resolved_images'));
        $useHero = (bool) $this->value($page, 'listing_use_as_hero');

        $variant = $useHero ? 'hero-banner' : 'listing-card';

        $resolved = $this->fromPayload($cropOverrides[$variant] ?? null, ['imageUrl', 'image_url', 'url', 'src'])
            ?? $this->fromPayload($resolvedImages[$variant] ?? null, ['image_url', 'imageUrl', 'url', 'src']);

        if ($resolved !== null) {
            return $resolved;
        }

        $preferredImageId = $useHero
            ? $this->value($page, 'hero_image_id')
            : $this->value($page, 'listing_image_id');

        $resolved = $this->fromImageId($preferredImageId);

        if ($resolved !== null) {
            return $resolved;
        }

        $secondaryImageId = $useHero
            ? $this->value($page, 'listing_image_id')
            : $this->value($page, 'hero_image_id');

        $resolved = $this->fromImageId($secondaryImageId);

        if ($resolved !== null) {
            return $resolved;
        }

        $resolved = $this->fromPayload($this->value($page, 'image'), ['url', 'image_url', 'imageUrl', 'src']);

        if ($resolved !== null) {
            return $resolved;
        }

        return $this->fromBlocks($this->value($page, 'blocks'));
    }

    /**
     * @return array{url: string, width: ?int, height: ?int}|null
     */
    private function fromBlocks(mixed $blocks): ?array
    {
        if (!is_iterable($blocks)) {
            return null;
        }

        foreach ($blocks as $block) {
            $type = $this->value($block, 'type');
            $data = $this->normaliseArray($this->value($block, 'data'));

            if ($type !== 'image' && !$this->containsImageReference($data)) {
                continue;
            }

            $resolved = $this->fromImageId($data['image_id'] ?? null)
                ?? $this->fromPayload($data, ['src', 'url', 'image_url', 'imageUrl']);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function containsImageReference(array $data): bool
    {
        return isset($data['image_id'])
            || isset($data['src'])
            || isset($data['image_url'])
            || isset($data['imageUrl']);
    }

    /**
     * @return array{url: string, width: ?int, height: ?int}|null
     */
    private function fromImageId(mixed $imageId): ?array
    {
        if (!is_numeric($imageId) || (int) $imageId <= 0) {
            return null;
        }

        $image = Image::find((int) $imageId);

        if (!$image || !$image->url) {
            return null;
        }

        return [
            'url' => (string) $image->url,
            'width' => $image->width ? (int) $image->width : null,
            'height' => $image->height ? (int) $image->height : null,
        ];
    }

    /**
     * @param array<int, string> $urlKeys
     * @return array{url: string, width: ?int, height: ?int}|null
     */
    private function fromPayload(mixed $payload, array $urlKeys): ?array
    {
        if (is_string($payload) && trim($payload) !== '') {
            return ['url' => trim($payload), 'width' => null, 'height' => null];
        }

        if (!is_array($payload) && !is_object($payload)) {
            return null;
        }

        foreach ($urlKeys as $urlKey) {
            $url = $this->value($payload, $urlKey);

            if (!is_string($url) || trim($url) === '') {
                continue;
            }

            return [
                'url' => trim($url),
                'width' => $this->positiveInteger($this->value($payload, 'width')),
                'height' => $this->positiveInteger($this->value($payload, 'height')),
            ];
        }

        return null;
    }

    private function value(object|array $source, string $key): mixed
    {
        return is_array($source) ? ($source[$key] ?? null) : ($source->{$key} ?? null);
    }

    private function normaliseArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function positiveInteger(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
