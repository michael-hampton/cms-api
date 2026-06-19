<?php

namespace App\Services\Cms;

use App\Models\Block;
use App\Models\Page;
use App\Models\PageGrid;
use App\Models\Product;
use App\Models\ProductImage;

final readonly class StoredContentImageMigration
{
    public function __construct(private ContentImageRewriter $rewriter)
    {
    }

    public function run(): array
    {
        return [
            'blocks' => $this->rewriteBlocks(),
            'page_grids' => $this->rewritePageGrids(),
            'galleries' => $this->rewriteGalleries(),
            'products' => $this->rewriteProducts(),
            'product_images' => $this->rewriteProductImages(),
        ];
    }

    private function rewriteBlocks(): int
    {
        $updated = 0;

        foreach (Block::all() as $block) {
            $page = Page::find($block->page_id);
            $original = $block->data;

            if (!$page || !$page->site_id || !is_array($original)) {
                continue;
            }

            $rewritten = $this->rewriter->rewrite($original, (int) $page->site_id);

            if ($rewritten === $original) {
                continue;
            }

            $block->data = $rewritten;
            $block->save();
            $updated++;
        }

        return $updated;
    }

    private function rewritePageGrids(): int
    {
        $updated = 0;

        foreach (PageGrid::all() as $grid) {
            $original = $grid->items;

            if (!$grid->site_id || !is_array($original)) {
                continue;
            }

            $rewritten = $this->rewriter->rewrite($original, (int) $grid->site_id);

            if ($rewritten === $original) {
                continue;
            }

            $grid->items = $rewritten;
            $grid->save();
            $updated++;
        }

        return $updated;
    }

    private function rewriteGalleries(): int
    {
        $updated = 0;

        foreach (Page::all() as $page) {
            $original = $page->gallery_slides;
            $slides = is_string($original) ? json_decode($original, true) : $original;

            if (!$page->site_id || !is_array($slides)) {
                continue;
            }

            $rewritten = $this->rewriter->rewrite($slides, (int) $page->site_id);

            if ($rewritten === $slides) {
                continue;
            }

            $page->gallery_slides = $rewritten;
            $page->save();
            $updated++;
        }

        return $updated;
    }

    private function rewriteProducts(): int
    {
        $updated = 0;

        foreach (Product::all() as $product) {
            if (!$product->site_id || !is_string($product->image)) {
                continue;
            }

            $rewritten = $this->rewriter->rewriteUrl(
                $product->image,
                (int) $product->site_id,
                $product->name
            );

            if ($rewritten === $product->image) {
                continue;
            }

            $product->image = $rewritten;
            $product->save();
            $updated++;
        }

        return $updated;
    }

    private function rewriteProductImages(): int
    {
        $updated = 0;

        foreach (ProductImage::all() as $productImage) {
            $product = Product::find($productImage->product_id);

            if (!$product || !$product->site_id || !is_string($productImage->url)) {
                continue;
            }

            $rewritten = $this->rewriter->rewriteUrl(
                $productImage->url,
                (int) $product->site_id,
                $productImage->alt ?? $product->name
            );

            if ($rewritten === $productImage->url) {
                continue;
            }

            $productImage->url = $rewritten;
            $productImage->save();
            $updated++;
        }

        return $updated;
    }
}
