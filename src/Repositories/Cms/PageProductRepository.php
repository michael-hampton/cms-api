<?php

namespace App\Repositories\Cms;

use App\Models\PageProduct;
use App\Repositories\Repository;

class PageProductRepository extends Repository
{
    protected function getModelClass(): string
    {
        return PageProduct::class;
    }

    public function syncProducts(int $pageId, array $productIds, int $siteId): void
    {
        // Remove existing products not in new list
        PageProduct::where('page_id', $pageId)
            ->when(!empty($productIds), function ($query) use ($productIds) {
                $query->whereNotIn('product_id', $productIds);
            })
            ->delete();

        // Add or update products with sort order
        foreach ($productIds as $index => $productId) {
            PageProduct::updateOrCreate(
                [
                    'page_id' => $pageId,
                    'product_id' => $productId
                ],
                [
                    'sort_order' => $index,
                    'site_id' => $siteId
                ]
            );
        }
    }

    public function getProductsForPage(int $pageId)
    {
        return PageProduct::where('page_id', $pageId)
            ->orderBy('sort_order')
            ->get();
    }
}