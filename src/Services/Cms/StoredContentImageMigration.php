<?php

namespace App\Services\Cms;

use App\Models\Block;
use App\Models\Page;
use App\Models\PageGrid;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Cms\Pages\PageCardImageResolver;
use Closure;
use Throwable;

final class StoredContentImageMigration
{
    private Closure $logger;
    private PageCardImageResolver $pageCardImageResolver;

    public function __construct(
        private ContentImageRewriter $rewriter,
        private UnsplashImageImporter $imageImporter,
        ?Closure $logger = null,
    ) {
        $this->logger = $logger ?? static function (string $message): void {
        };
        $this->pageCardImageResolver = new PageCardImageResolver();
    }

    public function run(): array
    {
        $this->log('Starting external image migration');

        $updated = [
            'blocks' => $this->rewriteBlocks(),
            'page_grids' => $this->rewritePageGrids(),
            'galleries' => $this->rewriteGalleries(),
            'products' => $this->rewriteProducts(),
            'product_images' => $this->rewriteProductImages(),
            'page_default_images' => $this->assignDefaultImagesToPages(),
        ];

        $failures = $this->rewriter->failures();
        $replacements = $this->rewriter->replacements();

        $this->log(sprintf(
            'Migration complete with %d fallback replacements and %d failures',
            count($replacements),
            count($failures)
        ));

        return [
            'updated' => $updated,
            'replacements' => $replacements,
            'failures' => $failures,
        ];
    }

    private function rewriteBlocks(): int
    {
        $blocks = Block::all();
        $total = $blocks->count();
        $updated = 0;

        $this->log("Scanning {$total} blocks");

        foreach ($blocks as $index => $block) {
            $position = $index + 1;
            $this->log("[blocks {$position}/{$total}] block #{$block->id}");

            $page = Page::find($block->page_id);
            $original = $block->data;

            if (!$page || !$page->site_id || !is_array($original)) {
                $this->log("[blocks {$position}/{$total}] skipped block #{$block->id}");
                continue;
            }

            $rewritten = $this->rewriter->rewrite($original, (int) $page->site_id);

            if ($rewritten === $original) {
                continue;
            }

            $block->data = $rewritten;
            $block->save();
            $updated++;
            $this->log("[blocks {$position}/{$total}] updated block #{$block->id}");
        }

        $this->log("Finished blocks: {$updated} updated");

        return $updated;
    }

    private function rewritePageGrids(): int
    {
        $grids = PageGrid::all();
        $total = $grids->count();
        $updated = 0;

        $this->log("Scanning {$total} page grids");

        foreach ($grids as $index => $grid) {
            $position = $index + 1;
            $this->log("[page-grids {$position}/{$total}] grid #{$grid->id}");

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
            $this->log("[page-grids {$position}/{$total}] updated grid #{$grid->id}");
        }

        $this->log("Finished page grids: {$updated} updated");

        return $updated;
    }

    private function rewriteGalleries(): int
    {
        $pages = Page::all();
        $total = $pages->count();
        $updated = 0;

        $this->log("Scanning gallery slides across {$total} pages");

        foreach ($pages as $index => $page) {
            $position = $index + 1;
            $this->log("[galleries {$position}/{$total}] page #{$page->id}");

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
            $this->log("[galleries {$position}/{$total}] updated page #{$page->id}");
        }

        $this->log("Finished galleries: {$updated} updated");

        return $updated;
    }

    private function rewriteProducts(): int
    {
        $products = Product::all();
        $total = $products->count();
        $updated = 0;

        $this->log("Scanning {$total} products");

        foreach ($products as $index => $product) {
            $position = $index + 1;
            $this->log("[products {$position}/{$total}] product #{$product->id}");

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
            $this->log("[products {$position}/{$total}] updated product #{$product->id}");
        }

        $this->log("Finished products: {$updated} updated");

        return $updated;
    }

    private function rewriteProductImages(): int
    {
        $productImages = ProductImage::all();
        $total = $productImages->count();
        $updated = 0;

        $this->log("Scanning {$total} product images");

        foreach ($productImages as $index => $productImage) {
            $position = $index + 1;
            $this->log("[product-images {$position}/{$total}] product image #{$productImage->id}");

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
            $this->log("[product-images {$position}/{$total}] updated product image #{$productImage->id}");
        }

        $this->log("Finished product images: {$updated} updated");

        return $updated;
    }

    private function assignDefaultImagesToPages(): int
    {
        $pages = Page::all();
        $total = $pages->count();
        $updated = 0;

        $this->log("Checking {$total} pages for missing card images");

        foreach ($pages as $index => $page) {
            $position = $index + 1;

            if (!$page->site_id) {
                $this->log("[page-defaults {$position}/{$total}] skipped page #{$page->id}: no site");
                continue;
            }

            if ($this->pageCardImageResolver->resolve($page) !== null) {
                $this->log("[page-defaults {$position}/{$total}] page #{$page->id} already has an image");
                continue;
            }

            $this->log("[page-defaults {$position}/{$total}] assigning default image to page #{$page->id}");

            try {
                $image = $this->imageImporter->import(
                    UnsplashImageImporter::DEFAULT_FALLBACK_URL,
                    (int) $page->site_id,
                    [
                        'name' => 'Default page image',
                        'alt_text' => $page->title ?: 'Default page image',
                        'description' => 'Automatically assigned because the page had no usable image.',
                    ]
                );
            } catch (Throwable $exception) {
                $this->log(sprintf(
                    '[page-defaults %d/%d] failed page #%d: %s',
                    $position,
                    $total,
                    $page->id,
                    $exception->getMessage()
                ));
                continue;
            }

            $page->listing_image_id = $image->id;
            $page->save();
            $updated++;

            $this->log(sprintf(
                '[page-defaults %d/%d] page #%d assigned image #%d',
                $position,
                $total,
                $page->id,
                $image->id
            ));
        }

        $this->log("Finished page defaults: {$updated} pages updated");

        return $updated;
    }

    private function log(string $message): void
    {
        ($this->logger)('[migration] ' . $message);
    }
}
