<?php

namespace App\Parsers;

use App\Parsers\Dtos\AccordionBlockDto;
use App\Parsers\Dtos\AgentProfileBlockDto;
use App\Parsers\Dtos\AwardBlockDto;
use App\Parsers\Dtos\BannerBlockDto;
use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\BoxoutBlockDto;
use App\Parsers\Dtos\BuyingGuideBlockDto;
use App\Parsers\Dtos\CardBlockDto;
use App\Parsers\Dtos\CardGroupBlockDto;
use App\Parsers\Dtos\ContactFormBlockDto;
use App\Parsers\Dtos\CtaBlockDto;
use App\Parsers\Dtos\DealBlockDto;
use App\Parsers\Dtos\DividerBlockDto;
use App\Parsers\Dtos\EventBlockDto;
use App\Parsers\Dtos\EventSignupBlockDto;
use App\Parsers\Dtos\GalleryBlockDto;
use App\Parsers\Dtos\GroupBlockDto;
use App\Parsers\Dtos\HeadingBlockDto;
use App\Parsers\Dtos\HeroBlockDto;
use App\Parsers\Dtos\ImageBlockDto;
use App\Parsers\Dtos\InfoBlockDto;
use App\Parsers\Dtos\ListBlockDto;
use App\Parsers\Dtos\MapLocationBlockDto;
use App\Parsers\Dtos\NewsFeedBlockDto;
use App\Parsers\Dtos\PageLinksBlockDto;
use App\Parsers\Dtos\PersonBlockDto;
use App\Parsers\Dtos\ProductBlockDto;
use App\Parsers\Dtos\ProductComparisonBlockDto;
use App\Parsers\Dtos\QuoteBlockDto;
use App\Parsers\Dtos\SchemaBlockDto;
use App\Parsers\Dtos\SectionBlockDto;
use App\Parsers\Dtos\ServicesBlockDto;
use App\Parsers\Dtos\StatsBlockDto;
use App\Parsers\Dtos\TableBlockDto;
use App\Parsers\Dtos\TeamBlockDto;
use App\Parsers\Dtos\TeaserBlockDto;
use App\Parsers\Dtos\TestimonialBlockDto;
use App\Parsers\Dtos\TextBlockDto;
use App\Parsers\Dtos\VideoBlockDto;
use App\Parsers\Dtos\ZoneBlockDto;
use InvalidArgumentException;

class BlockFactory
{
    /** @var array<string, class-string<BlockDtoInterface>> */
    private array $map = [
        'accordion' => AccordionBlockDto::class,
        'agent-profile' => AgentProfileBlockDto::class,
        'award' => AwardBlockDto::class,
        'banner' => BannerBlockDto::class,
        'box-out' => BoxoutBlockDto::class,
        'buying-guide' => BuyingGuideBlockDto::class,
        'card' => CardBlockDto::class,
        'card-group' => CardGroupBlockDto::class,
        'contact-form' => ContactFormBlockDto::class,
        'cta' => CtaBlockDto::class,
        'deal' => DealBlockDto::class,
        'divider' => DividerBlockDto::class,
        'event' => EventBlockDto::class,
        'event-signup' => EventSignupBlockDto::class,
        'gallery' => GalleryBlockDto::class,
        'group' => GroupBlockDto::class,
        'heading' => HeadingBlockDto::class,
        'hero' => HeroBlockDto::class,
        'image' => ImageBlockDto::class,
        'info' => InfoBlockDto::class,
        'list' => ListBlockDto::class,
        'map-location' => MapLocationBlockDto::class,
        'news-feed' => NewsFeedBlockDto::class,
        'note' => BoxoutBlockDto::class,
        'page-links' => PageLinksBlockDto::class,
        'person' => PersonBlockDto::class,
        'product' => ProductBlockDto::class,
        'product-comparison' => ProductComparisonBlockDto::class,
        'quote' => QuoteBlockDto::class,
        'schema' => SchemaBlockDto::class,
        'section' => SectionBlockDto::class,
        'services' => ServicesBlockDto::class,
        'stats' => StatsBlockDto::class,
        'table' => TableBlockDto::class,
        'team' => TeamBlockDto::class,
        'teaser' => TeaserBlockDto::class,
        'testimonial' => TestimonialBlockDto::class,
        'text' => TextBlockDto::class,
        'video' => VideoBlockDto::class,
        'zone' => ZoneBlockDto::class,
    ];

    /**
     * Create a Block DTO from raw block data
     *
     * @throws InvalidArgumentException when block type is unknown
     */
    public function make(array $data): BlockDtoInterface
    {
        $type = $data['type'] ?? null;

        if (!$type) {
            throw new InvalidArgumentException('Block type is required');
        }

        $class = $this->map[$type] ?? null;

        if (!$class) {
            throw new InvalidArgumentException("Unknown block type: {$type}");
        }

        return $class::fromArray($data);
    }

    /**
     * Check if the factory supports the given block type
     */
    public function supports(string $type): bool
    {
        return isset($this->map[$type]);
    }
}
