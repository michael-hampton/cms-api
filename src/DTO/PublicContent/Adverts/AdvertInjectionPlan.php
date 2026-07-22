<?php

namespace App\DTO\PublicContent\Adverts;

use App\DTO\PublicContent\Sources\SourceResult;

/**
 * Density-based advert plan: HTML injection offsets plus document-inspectable slots.
 */
final readonly class AdvertInjectionPlan
{
    /**
     * @param list<string> $inlineHtmlByMainBlockIndex HTML keyed by 1-based main-block ordinal after which to inject
     * @param list<string> $overflowHtml leftover adverts after inline capacity
     * @param list<AdvertSlot> $slots structured slots for the API document
     */
    public function __construct(
        public array $inlineHtmlByMainBlockIndex,
        public array $overflowHtml,
        public array $slots,
        public SourceResult $source,
        public int $mainBlockCount,
        public int $minGap,
        public int $maxInlineAdverts,
    ) {
    }

    public static function none(): self
    {
        return new self(
            inlineHtmlByMainBlockIndex: [],
            overflowHtml: [],
            slots: [],
            source: SourceResult::empty(),
            mainBlockCount: 0,
            minGap: 0,
            maxInlineAdverts: 0,
        );
    }

    /** @return array<string, mixed> */
    public function toDocumentArray(): array
    {
        return [
            'status' => $this->source->status->value,
            'reason' => $this->source->reason,
            'main_block_count' => $this->mainBlockCount,
            'min_gap' => $this->minGap,
            'max_inline_adverts' => $this->maxInlineAdverts,
            'slots' => array_map(
                static fn(AdvertSlot $slot): array => $slot->toArray(),
                $this->slots,
            ),
        ];
    }
}
