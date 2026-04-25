<?php

namespace App\Services\Newsletter;

/**
 * Maps the email template editor block types (Phase 1) to the block types
 * understood by the newsletter BlockDataFactory and EmailBlockRendererRegistry.
 *
 * The template editor uses simplified type names (text, image, button, etc.).
 * The newsletter rendering pipeline uses the same names in most cases, but
 * the template block data shapes differ slightly, so this registry:
 *
 *   1. Declares which Phase 1 block types are valid.
 *   2. Translates template block data into the shape BlockDataFactory expects.
 *   3. Maps template types to their newsletter renderer counterparts.
 *
 * Mapping table:
 *   Template type      → Newsletter renderer type
 *   ──────────────────────────────────────────────
 *   text               → text          (paragraphs array)
 *   image              → image         (src, alt, layout, alignment...)
 *   button             → cta           (text, url, ctaStyle, alignment)
 *   divider            → divider       (lineStyle)
 *   spacer             → (custom HTML — no newsletter equivalent)
 *   single_column      → (structural wrapper — no renderer needed)
 *   two_column         → (structural wrapper — no renderer needed)
 *   product_card       → product       (name, description, price, link...)
 *   order_summary      → (custom HTML — dynamic at runtime)
 *   ad_slot            → (resolved by AdResolver — not passed to renderers)
 */
class EmailTemplateBlockRegistry
{
    /**
     * All valid Phase 1 block types that the template editor accepts.
     */
    private const VALID_TYPES = [
        'text',
        'image',
        'button',
        'divider',
        'spacer',
        'single_column',
        'two_column',
        'product_card',
        'order_summary',
        'ad_slot',
    ];

    /**
     * Types that are handled by the newsletter BlockDataFactory + renderers.
     * Types NOT in this map are handled natively in EmailTemplateRenderer.
     */
    private const NEWSLETTER_TYPE_MAP = [
        'text' => 'text',
        'image' => 'image',
        'button' => 'cta',
        'divider' => 'divider',
        'product_card' => 'product',
    ];

    /**
     * Types resolved natively without going through the newsletter pipeline.
     */
    private const NATIVE_TYPES = [
        'spacer',
        'single_column',
        'two_column',
        'order_summary',
        'ad_slot',
    ];

    public function isValid(string $type): bool
    {
        return in_array($type, self::VALID_TYPES, true);
    }

    public function isNative(string $type): bool
    {
        return in_array($type, self::NATIVE_TYPES, true);
    }

    public function getNewsletterType(string $templateType): ?string
    {
        return self::NEWSLETTER_TYPE_MAP[$templateType] ?? null;
    }

    /**
     * Translate a template block's raw data into the shape BlockDataFactory expects
     * for its mapped newsletter type.
     *
     * Returns null for native types (caller handles those directly).
     */
    public function normaliseBlockData(string $templateType, array $data): ?array
    {
        return match ($templateType) {
            'text' => $this->normaliseTextBlock($data),
            'image' => $this->normaliseImageBlock($data),
            'button' => $this->normaliseButtonBlock($data),
            'divider' => $this->normaliseDividerBlock($data),
            'product_card' => $this->normaliseProductCardBlock($data),
            default => null,
        };
    }

    // ── Normalisers ───────────────────────────────────────────

    private function normaliseTextBlock(array $data): array
    {
        $content = $data['content'] ?? '';
        $align = $data['align'] ?? 'left';

        // TextBlockData expects paragraphs array
        // Honour alignment by wrapping in a div when non-left
        $html = $align !== 'left'
            ? '<div style="text-align:' . htmlspecialchars($align) . ';">' . $content . '</div>'
            : $content;

        return [
            'paragraphs' => [$html],
            'style' => $this->resolveFontSize($data['size'] ?? 'md'),
        ];
    }

    private function resolveFontSize(string $size): array
    {
        return match ($size) {
            'sm' => ['fontSize' => 'sm'],
            'lg' => ['fontSize' => 'lg'],
            default => [],
        };
    }

    private function normaliseImageBlock(array $data): array
    {
        return [
            'src' => $data['url'] ?? '',
            'alt' => $data['alt'] ?? '',
            'linkUrl' => $data['link'] ?? null,
            'layout' => 'full',
            'alignment' => $data['align'] ?? 'center',
            'caption' => null,
            'credit' => null,
            'noFollow' => false,
            'sponsored' => false,
            'openInNewTab' => false,
            'endorsements' => [],
        ];
    }

    private function normaliseButtonBlock(array $data): array
    {
        return [
            'text' => $data['label'] ?? 'Click here',
            'url' => $data['url'] ?? '#',
            'ctaStyle' => $data['style'] ?? 'primary',
            'size' => 'medium',
            'alignment' => $data['align'] ?? 'center',
            'noFollow' => false,
            'sponsored' => false,
            'openInNewTab' => false,
        ];
    }

    private function normaliseDividerBlock(array $data): array
    {
        return [
            'lineStyle' => $data['style'] ?? 'solid',
        ];
    }

    // ── Helpers ───────────────────────────────────────────────

    private function normaliseProductCardBlock(array $data): array
    {
        return [
            'name' => $data['name'] ?? '',
            'description' => $data['description'] ?? null,
            'price' => $this->extractPrice($data['price'] ?? '0'),
            'salePrice' => null,
            'currency' => '$',
            'link' => $data['url'] ?? null,
            'linkText' => 'View Product',
            'image' => !empty($data['image_url']) ? ['src' => $data['image_url']] : null,
        ];
    }

    private function extractPrice(string $priceString): float
    {
        return (float)preg_replace('/[^0-9.]/', '', $priceString);
    }
}