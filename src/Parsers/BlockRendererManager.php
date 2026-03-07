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
use App\Parsers\Renderers\AccordionBlockRenderer;
use App\Parsers\Renderers\AgentProfileBlockRenderer;
use App\Parsers\Renderers\AwardBlockRenderer;
use App\Parsers\Renderers\BannerBlockRenderer;
use App\Parsers\Renderers\BlockRendererInterface;
use App\Parsers\Renderers\BoxoutBlockRenderer;
use App\Parsers\Renderers\BuyingGuideBlockRenderer;
use App\Parsers\Renderers\CardBlockRenderer;
use App\Parsers\Renderers\CardGroupBlockRenderer;
use App\Parsers\Renderers\ContactFormBlockRenderer;
use App\Parsers\Renderers\CtaBlockRenderer;
use App\Parsers\Renderers\DealBlockRenderer;
use App\Parsers\Renderers\DividerBlockRenderer;
use App\Parsers\Renderers\EventBlockRenderer;
use App\Parsers\Renderers\EventSignupBlockRenderer;
use App\Parsers\Renderers\GalleryBlockRenderer;
use App\Parsers\Renderers\GroupBlockRenderer;
use App\Parsers\Renderers\HeadingBlockRenderer;
use App\Parsers\Renderers\HeroBlockRenderer;
use App\Parsers\Renderers\ImageBlockRenderer;
use App\Parsers\Renderers\InfoBlockRenderer;
use App\Parsers\Renderers\ListBlockRenderer;
use App\Parsers\Renderers\MapLocationBlockRenderer;
use App\Parsers\Renderers\NewsFeedBlockRenderer;
use App\Parsers\Renderers\PageLinksBlockRenderer;
use App\Parsers\Renderers\PersonBlockRenderer;
use App\Parsers\Renderers\ProductBlockRenderer;
use App\Parsers\Renderers\ProductComparisonBlockRenderer;
use App\Parsers\Renderers\QuoteBlockRenderer;
use App\Parsers\Renderers\SchemaBlockRenderer;
use App\Parsers\Renderers\SectionBlockRenderer;
use App\Parsers\Renderers\ServicesBlockRenderer;
use App\Parsers\Renderers\StatsBlockRenderer;
use App\Parsers\Renderers\TableBlockRenderer;
use App\Parsers\Renderers\TeamBlockRenderer;
use App\Parsers\Renderers\TeaserBlockRenderer;
use App\Parsers\Renderers\TestimonialBlockRenderer;
use App\Parsers\Renderers\TextBlockRenderer;
use App\Parsers\Renderers\VideoBlockRenderer;
use App\Parsers\Renderers\ZoneBlockRenderer;
use RuntimeException;

class BlockRendererManager
{
    /** @var array<class-string<BlockDtoInterface>, class-string<BlockRendererInterface>> */
    private array $map = [
        AccordionBlockDto::class => AccordionBlockRenderer::class,
        AgentProfileBlockDto::class => AgentProfileBlockRenderer::class,
        AwardBlockDto::class => AwardBlockRenderer::class,
        BannerBlockDto::class => BannerBlockRenderer::class,
        BoxoutBlockDto::class => BoxoutBlockRenderer::class,
        BuyingGuideBlockDto::class => BuyingGuideBlockRenderer::class,
        CardBlockDto::class => CardBlockRenderer::class,
        CardGroupBlockDto::class => CardGroupBlockRenderer::class,
        ContactFormBlockDto::class => ContactFormBlockRenderer::class,
        CtaBlockDto::class => CtaBlockRenderer::class,
        DealBlockDto::class => DealBlockRenderer::class,
        DividerBlockDto::class => DividerBlockRenderer::class,
        EventBlockDto::class => EventBlockRenderer::class,
        EventSignupBlockDto::class => EventSignupBlockRenderer::class,
        GalleryBlockDto::class => GalleryBlockRenderer::class,
        GroupBlockDto::class => GroupBlockRenderer::class,
        HeadingBlockDto::class => HeadingBlockRenderer::class,
        HeroBlockDto::class => HeroBlockRenderer::class,
        ImageBlockDto::class => ImageBlockRenderer::class,
        InfoBlockDto::class => InfoBlockRenderer::class,
        ListBlockDto::class => ListBlockRenderer::class,
        MapLocationBlockDto::class => MapLocationBlockRenderer::class,
        NewsFeedBlockDto::class => NewsFeedBlockRenderer::class,
        PageLinksBlockDto::class => PageLinksBlockRenderer::class,
        PersonBlockDto::class => PersonBlockRenderer::class,
        ProductBlockDto::class => ProductBlockRenderer::class,
        ProductComparisonBlockDto::class => ProductComparisonBlockRenderer::class,
        QuoteBlockDto::class => QuoteBlockRenderer::class,
        SchemaBlockDto::class => SchemaBlockRenderer::class,
        SectionBlockDto::class => SectionBlockRenderer::class,
        ServicesBlockDto::class => ServicesBlockRenderer::class,
        StatsBlockDto::class => StatsBlockRenderer::class,
        TableBlockDto::class => TableBlockRenderer::class,
        TeamBlockDto::class => TeamBlockRenderer::class,
        TeaserBlockDto::class => TeaserBlockRenderer::class,
        TestimonialBlockDto::class => TestimonialBlockRenderer::class,
        TextBlockDto::class => TextBlockRenderer::class,
        VideoBlockDto::class => VideoBlockRenderer::class,
        ZoneBlockDto::class => ZoneBlockRenderer::class,
    ];

    /**
     * Render a block DTO to HTML
     *
     * @param BlockDtoInterface $block The block DTO to render
     * @param int|null $pageId Optional page ID for context-aware renderers (e.g. HeroBlockRenderer)
     * @param int|null $siteId Optional site ID for context-aware renderers
     * @throws RuntimeException when no renderer is found for the block type
     */
    public function render(BlockDtoInterface $block, ?int $pageId = null, ?int $siteId = null): string
    {
        $rendererClass = $this->map[$block::class] ?? null;

        if (!$rendererClass) {
            throw new RuntimeException('Renderer not found for block type: ' . $block->getType());
        }

        $renderer = app($rendererClass);

        if (!$renderer instanceof BlockRendererInterface) {
            throw new RuntimeException("Renderer {$rendererClass} does not implement BlockRendererInterface");
        }

        if ($renderer instanceof HeroBlockRenderer && $pageId !== null) {
            return $renderer->render($block, $pageId);
        }

        return $renderer->render($block);
    }

    /**
     * Check if a renderer exists for the given DTO class
     */
    public function supports(BlockDtoInterface $block): bool
    {
        return isset($this->map[$block::class]);
    }
}
