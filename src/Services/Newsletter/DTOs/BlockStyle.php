<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs;

/**
 * Carries optional visual overrides that apply to any newsletter block.
 *
 * The newsletter block builder stores these under `block.data['style']`.
 * Every renderer receives a (possibly default) BlockStyle and applies it
 * to the block's outermost wrapper element.
 *
 * All properties are nullable — null means "use the renderer default".
 */
final class BlockStyle
{
    public function __construct(
        /**
         * Overall font size for the block content.
         * Accepted values: 'sm' | 'base' | 'lg' | 'xl' | '2xl'
         * Resolved to px by BlockStyle::fontSizePx().
         */
        public readonly ?string $fontSize = null,

        /**
         * Text colour for the entire block (hex string, e.g. '#333333').
         * Can be overridden at the inline level by the WYSIWYG editor
         * using <span style="color:…"> on selected text.
         */
        public readonly ?string $color = null,

        /**
         * Padding applied to the block wrapper (CSS shorthand or single value).
         * Examples: '16px', '8px 16px', '0 0 24px 0'
         */
        public readonly ?string $padding = null,

        /**
         * Background colour for the block wrapper (hex string).
         */
        public readonly ?string $backgroundColor = null,
    )
    {
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    /**
     * Build from the raw `style` array stored in block data.
     * Returns a fully-defaulted instance when no style is provided.
     */
    public static function fromArray(?array $data): self
    {
        if (empty($data)) {
            return new self();
        }

        return new self(
            fontSize: $data['fontSize'] ?? null,
            color: $data['color'] ?? null,
            padding: $data['padding'] ?? null,
            backgroundColor: $data['backgroundColor'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'fontSize' => $this->fontSize,
            'color' => $this->color,
            'padding' => $this->padding,
            'backgroundColor' => $this->backgroundColor,
        ], fn($v) => $v !== null);
    }

    // -------------------------------------------------------------------------
    // Helpers used by renderers
    // -------------------------------------------------------------------------

    /**
     * Returns true if this style has any non-null value.
     */
    public function isEmpty(): bool
    {
        return $this->fontSize === null
            && $this->color === null
            && $this->padding === null
            && $this->backgroundColor === null;
    }

    /**
     * Merge this style into an existing inline style string.
     *
     * Existing declarations are preserved; BlockStyle values are appended
     * (and will override if the property is repeated, per CSS cascade rules).
     */
    public function mergeIntoCss(string $existingCss): string
    {
        $overrides = $this->toWrapperCss();

        if ($overrides === '') {
            return $existingCss;
        }

        $base = rtrim($existingCss, ';');
        return $base !== '' ? "{$base};{$overrides}" : $overrides;
    }

    /**
     * Build the inline CSS string for the block wrapper div.
     *
     * Example output: "font-size:18px;color:#333;padding:16px;"
     *
     * Renderers should use this on their outermost wrapping element when
     * they don't have their own padding / background already applied.
     */
    public function toWrapperCss(): string
    {
        $parts = [];

        if (($px = $this->fontSizePx()) !== null) {
            $parts[] = "font-size:{$px}";
        }

        if ($this->color !== null) {
            $parts[] = "color:{$this->color}";
        }

        if ($this->padding !== null) {
            $parts[] = "padding:{$this->padding}";
        }

        if ($this->backgroundColor !== null) {
            $parts[] = "background-color:{$this->backgroundColor}";
        }

        return empty($parts) ? '' : implode(';', $parts) . ';';
    }

    /**
     * Resolved pixel size for the fontSize token.
     * Returns null if no fontSize is set (renderer uses its own default).
     */
    public function fontSizePx(): ?string
    {
        return match ($this->fontSize) {
            'sm' => '13px',
            'base' => '16px',
            'lg' => '18px',
            'xl' => '20px',
            '2xl' => '24px',
            default => null,
        };
    }
}