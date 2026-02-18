<?php

namespace App\Imports;

use App\Framework\Database\Database;
use App\Repositories\Product\MerchantProductRepository;
use App\Repositories\Product\ProductRepositoryInterface;

final class MerchantProductImport extends BaseMerchantImport
{
    public function __construct(
        Database                                    $database,
        CsvParser                                   $csvParser,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly MerchantProductRepository  $merchantProductRepository
    )
    {
        parent::__construct($database, $csvParser);
    }

    protected function requiredColumns(): array
    {
        return ['name', 'price', 'category_id'];
    }

    protected function importRow(array $row): void
    {
        $name = trim($row['name']);
        $price = $this->parseNonNegativeFloat($row['price'], 'price');
        $categoryId = $this->parseNonNegativeInt($row['category_id'], 'category_id');

        $salePrice = isset($row['sale_price']) && $row['sale_price'] !== ''
            ? $this->parseNonNegativeFloat($row['sale_price'], 'sale_price')
            : null;

        $stockQuantity = isset($row['stock_quantity']) && $row['stock_quantity'] !== ''
            ? $this->parseNonNegativeInt($row['stock_quantity'], 'stock_quantity')
            : 0;

        $dispatchDays = isset($row['dispatch_days']) && $row['dispatch_days'] !== ''
            ? $this->parseNonNegativeInt($row['dispatch_days'], 'dispatch_days')
            : null;

        $existing = $this->merchantProductRepository->findByNameAndMerchant($name, $this->importOptions->merchantId);

        $productData = [
            'name' => $name,
            'price' => $price,
            'sale_price' => $salePrice ?? 0,
            'category_id' => $categoryId,
            'brand_id' => isset($row['brand_id']) && $row['brand_id'] !== ''
                ? $this->parseNonNegativeInt($row['brand_id'], 'brand_id')
                : null,
            'stock_quantity' => $stockQuantity,
            'dispatch_days' => $dispatchDays ?? 0,
            'is_active' => true,
            'site_id' => $this->importOptions->siteId,
            'description' => $row['description'] ?? null,
            'slug' => $row['slug'] ?? null,
        ];

        if ($existing) {
            if (!$this->importOptions->updateExisting) {
                throw new SkippableRowException(
                    "Product '{$name}' already exists for merchant {$this->importOptions->merchantId}. Skipping (updateExisting=false)."
                );
            }

            $this->productRepository->update($existing->product_id, $productData);
            return;
        }

        $product = $this->productRepository->create($productData);

        $this->merchantProductRepository->create([
            'product_id' => $product->id,
            'merchant_id' => $this->importOptions->merchantId,
            'price' => $price,
            'sale_price' => $salePrice,
            'is_available' => true,
            'url' => 'test' //todo
        ]);
    }
}