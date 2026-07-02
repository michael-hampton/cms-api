<?php
namespace App\Services\PublicContent;

use App\DTO\PublicContent\PageReviewData;
use App\Models\Page;

class PageReviewDataFactory
{
    public function fromPage(Page $page): ?PageReviewData
    {
        $raw = $page->review_data;

        if (!is_array($raw) || $raw === []) {
            return null;
        }

        return $this->fromArray($raw);
    }

    public function fromArray(array $raw): PageReviewData
    {
        return new PageReviewData(
            rating: $this->clampRating((float) ($raw['rating'] ?? 0)),
            maxRating: max(1, (int) ($raw['max_rating'] ?? $raw['maxRating'] ?? 5)),
            subRating: $this->clampRating((float) ($raw['sub_rating'] ?? $raw['subRating'] ?? 0)), // <-- FIX: Extract & clamp sub-rating
            product: $this->nullableString($raw['product'] ?? null),
            category: $this->nullableString($raw['category'] ?? null),
            verdict: (string) ($raw['verdict'] ?? ''),
            pros: $this->stringList($raw['pros'] ?? []),
            cons: $this->stringList($raw['cons'] ?? []),
        );
    }

    private function clampRating(float $rating): float
    {
        return max(0.0, min(5.0, $rating));
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    private function stringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn(mixed $v): string => trim((string) $v),
            $values,
        ), static fn(string $v): bool => $v !== ''));
    }
}