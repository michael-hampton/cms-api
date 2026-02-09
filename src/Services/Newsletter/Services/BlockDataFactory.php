<?php

namespace App\Services\Newsletter\Services;

use App\Services\Newsletter\DTOs\BlockData\AwardBlockData;
use App\Services\Newsletter\DTOs\BlockData\BannerBlockData;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\BuyingGuideBlockData;
use App\Services\Newsletter\DTOs\BlockData\ContactFormBlockData;
use App\Services\Newsletter\DTOs\BlockData\CtaBlockData;
use App\Services\Newsletter\DTOs\BlockData\DealBlockData;
use App\Services\Newsletter\DTOs\BlockData\DividerBlockData;
use App\Services\Newsletter\DTOs\BlockData\HeadingBlockData;
use App\Services\Newsletter\DTOs\BlockData\HeroBlockData;
use App\Services\Newsletter\DTOs\BlockData\ImageBlockData;
use App\Services\Newsletter\DTOs\BlockData\InfoBlockData;
use App\Services\Newsletter\DTOs\BlockData\ListBlockData;
use App\Services\Newsletter\DTOs\BlockData\NoteBlockData;
use App\Services\Newsletter\DTOs\BlockData\OfferBlockData;
use App\Services\Newsletter\DTOs\BlockData\PersonBlockData;
use App\Services\Newsletter\DTOs\BlockData\ProductBlockData;
use App\Services\Newsletter\DTOs\BlockData\ProductComparisonBlockData;
use App\Services\Newsletter\DTOs\BlockData\QuoteBlockData;
use App\Services\Newsletter\DTOs\BlockData\RewardBlockData;
use App\Services\Newsletter\DTOs\BlockData\SchemaBlockData;
use App\Services\Newsletter\DTOs\BlockData\SectionBlockData;
use App\Services\Newsletter\DTOs\BlockData\StaticDealBlockData;
use App\Services\Newsletter\DTOs\BlockData\StatsBlockData;
use App\Services\Newsletter\DTOs\BlockData\TableBlockData;
use App\Services\Newsletter\DTOs\BlockData\TestimonialBlockData;
use App\Services\Newsletter\DTOs\BlockData\TextBlockData;

class BlockDataFactory
{
    public function create(string $type, array $data): BaseBlockData
    {
        return match ($type) {
            'offer-deal' => DealBlockData::fromArray($data),
            'offer' => OfferBlockData::fromArray($data),
            'reward' => RewardBlockData::fromArray($data),
            'text' => TextBlockData::fromArray($data),
            'heading' => HeadingBlockData::fromArray($data),
            'image' => ImageBlockData::fromArray($data),
            'list' => ListBlockData::fromArray($data),
            'quote' => QuoteBlockData::fromArray($data),
            'cta' => CtaBlockData::fromArray($data),
            'product' => ProductBlockData::fromArray($data),
            'table' => TableBlockData::fromArray($data),
            'stats' => StatsBlockData::fromArray($data),
            'testimonial' => TestimonialBlockData::fromArray($data),
            'divider' => DividerBlockData::fromArray($data),
            'banner' => BannerBlockData::fromArray($data),
            'hero' => HeroBlockData::fromArray($data),
            'info' => InfoBlockData::fromArray($data),
            'section' => SectionBlockData::fromArray($data),
            'person' => PersonBlockData::fromArray($data),
            'product-comparison' => ProductComparisonBlockData::fromArray($data),
            'schema' => SchemaBlockData::fromArray($data),
            'award' => AwardBlockData::fromArray($data),
            'note' => NoteBlockData::fromArray($data),
            'buying-guide' => BuyingGuideBlockData::fromArray($data),
            'contact-form' => ContactFormBlockData::fromArray($data),
            'deal' => StaticDealBlockData::fromArray($data),
            default => throw new \InvalidArgumentException("Unknown block type: {$type}")
        };
    }
}