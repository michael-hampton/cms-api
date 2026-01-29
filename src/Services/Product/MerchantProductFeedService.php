<?php

namespace App\Services\Product;

use App\Framework\HttpClient\HttpClient;
use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Models\Model;
use App\Repositories\Product\MerchantProductFeedRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\Product\FeedMappers\AmazonFeedMapper;
use App\Services\Product\FeedMappers\EbayFeedMapper;
use App\Services\Product\FeedMappers\FeedMapperRegistry;
use Exception;

class MerchantProductFeedService
{
    private FeedMapperRegistry $mapperRegistry;
    private MerchantProductFeedRepository $repository;
    private ?int $currentFeedId = null;


    public function __construct(
        MerchantProductFeedRepository      $repository,
        private readonly ProductRepository $productRepository,
        private readonly ProductService    $productService,
        private readonly HttpClient $httpClient,
        ?FeedMapperRegistry         $mapperRegistry = null
    )
    {
        $this->repository = $repository;
        $this->mapperRegistry = $mapperRegistry ?? $this->createDefaultMapperRegistry();
    }

    public function getFeed(int $id): ?Model
    {
        return $this->repository->find($id);
    }

    public function createFeed(array $data): Model
    {
        // Set default values
        if (!isset($data['status'])) {
            $data['status'] = 'pending';
        }

        if (!isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        // Calculate next fetch time based on frequency
        if (isset($data['fetch_frequency']) && $data['fetch_frequency'] !== 'manual') {
            $data['next_fetch_at'] = $this->calculateNextFetchTime($data['fetch_frequency']);
        }

        return $this->repository->create($data);
    }

    protected function calculateNextFetchTime(string $frequency): ?string
    {
        $now = time();

        switch ($frequency) {
            case 'hourly':
                $next = strtotime('+1 hour', $now);
                break;
            case 'daily':
                $next = strtotime('+1 day', $now);
                break;
            case 'weekly':
                $next = strtotime('+1 week', $now);
                break;
            default:
                return null;
        }

        return date('Y-m-d H:i:s', $next);
    }

    public function updateFeed(int $id, array $data): ?Model
    {
        $feed = $this->repository->find($id);

        if (!$feed) {
            return null;
        }

        // Recalculate next fetch time if frequency changed
        if (isset($data['fetch_frequency']) && $data['fetch_frequency'] !== 'manual') {
            $data['next_fetch_at'] = $this->calculateNextFetchTime($data['fetch_frequency']);
        }

        return $this->repository->update($id, $data);
    }

    public function deleteFeed(int $id): bool
    {
        $feed = $this->repository->find($id);

        if (!$feed) {
            return false;
        }

        return $this->repository->delete($id);
    }

    public function fetchFeed(int $id): ?Model
    {
        $feed = $this->repository->find($id);

        if (!$feed) {
            return null;
        }

        $this->currentFeedId = $id;

        try {
            // Update status to processing
            $this->repository->update($id, [
                'status' => 'processing',
                'last_error' => null
            ]);

            // Download feed data
            $feedData = $this->downloadExternalFeedData($id);

            // Parse feed based on type
            $products = $this->parseFeedData($feedData, $feed->feed_type);

            // Process products (create/update in database)
            $processedCount = $this->processFeedProducts($products, $feed->merchant_id);

            // Update feed status
            $updateData = [
                'status' => 'success',
                'last_fetched_at' => date('Y-m-d H:i:s'),
                'next_fetch_at' => $this->calculateNextFetchTime($feed->fetch_frequency),
                'products_processed' => $processedCount
            ];

            return $this->repository->update($id, $updateData);
        } catch (Exception $e) {
            die('here5');
            Logger::error('Feed fetch failed: ' . $e->getMessage(), [
                'feed_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            $this->repository->update($id, [
                'status' => 'failed',
                'last_error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function downloadExternalFeedData(int $id): string
    {
        $feed = $this->repository->find($id);

        if (!$feed) {
            throw new Exception('Feed not found');
        }

        if (!$feed->feed_url) {
            throw new Exception('Feed URL is not configured');
        }

        try {
            $response = $this->httpClient->get($feed->feed_url, [
                'timeout' => 60,
                'headers' => [
                    'User-Agent' => 'MerchantProductFeedBot/1.0'
                ]
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new Exception('Failed to download feed: HTTP ' . $response->getStatusCode());
            }

            $content = $response->getBody();

            // Validate content is not empty
            if (empty($content)) {
                throw new Exception('Feed returned empty content');
            }

            // Validate content type matches feed type
            $this->validateFeedContent($content, $feed->feed_type);

            return $content;
        } catch (Exception $e) {
            Logger::error('Failed to download feed', [
                'feed_id' => $id,
                'url' => $feed->feed_url,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Failed to download feed: ' . $e->getMessage());
        }
    }

    protected function validateFeedContent(string $content, string $feedType): void
    {
        switch ($feedType) {
            case 'xml':
                libxml_use_internal_errors(true);
                $xml = simplexml_load_string($content);
                if ($xml === false) {
                    $errors = libxml_get_errors();
                    libxml_clear_errors();
                    throw new Exception('Invalid XML: ' . ($errors[0]->message ?? 'Unknown error'));
                }
                break;
            case 'json':
                json_decode($content);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Invalid JSON: ' . json_last_error_msg());
                }
                break;
            case 'csv':
                // Basic CSV validation - check if it has at least one line
                $lines = str_getcsv($content, "\n");
                if (count($lines) < 1) {
                    throw new Exception('Invalid CSV: No data found');
                }
                break;
        }
    }

    protected function parseFeedData(string $data, string $feedType): array
    {
        switch ($feedType) {
            case 'xml':
                return $this->parseXmlFeed($data);
            case 'json':
                return $this->parseJsonFeed($data);
            case 'csv':
                return $this->parseCsvFeed($data);
            default:
                throw new Exception('Unsupported feed type: ' . $feedType);
        }
    }

    protected function parseXmlFeed(string $data): array
    {
        $xml = simplexml_load_string($data);
        $products = [];
        $feed = $this->repository->find($this->currentFeedId ?? 0);
        $mapper = $this->mapperRegistry->getMapper($feed->feed_url ?? '', $feed->merchant_id ?? 0);

        foreach ($xml->product as $product) {
            $productArray = json_decode(json_encode($product), true);
            $products[] = $mapper->map($productArray);
        }

        return $products;
    }

    protected function parseJsonFeed(string $data): array
    {
        $json = json_decode($data, true);

        if (!isset($json['products']) || !is_array($json['products'])) {
            throw new Exception('Invalid JSON feed structure: missing products array');
        }

        $feed = $this->repository->find($this->currentFeedId ?? 0);
        $mapper = $this->mapperRegistry->getMapper($feed->feed_url ?? '', $feed->merchant_id ?? 0);

        return array_map(function ($product) use ($mapper) {
            return $mapper->map($product);
        }, $json['products']);
    }

    protected function parseCsvFeed(string $data): array
    {
        $lines = array_map('str_getcsv', explode("\n", $data));
        $header = array_shift($lines);
        $products = [];

        $feed = $this->repository->find($this->currentFeedId ?? 0);
        $mapper = $this->mapperRegistry->getMapper($feed->feed_url ?? '', $feed->merchant_id ?? 0);

        foreach ($lines as $line) {
            if (count($line) !== count($header)) {
                continue;
            }

            $product = array_combine($header, $line);
            $products[] = $mapper->map($product);
        }

        return $products;
    }

    protected function processFeedProducts(array $products, int $merchantId): int
    {
        $processedCount = 0;

        foreach ($products as $productData) {
            try {
                // Find or create product by SKU
                $existingProduct = $this->productRepository->findBySku($productData['sku']);

                if ($existingProduct) {
                    // Update existing product merchant relationship
                    $this->updateProductMerchant($existingProduct->id, $merchantId, $productData);
                } else {
                    // Create new product
                    $this->createProductFromFeed($productData, $merchantId);
                }

                $processedCount++;
            } catch (Exception $e) {
                Logger::error('Failed to process feed product', [
                    'sku' => $productData['sku'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
                // Continue processing other products
            }
        }

        return $processedCount;
    }

    protected function updateProductMerchant(int $productId, int $merchantId, array $productData): void
    {
        $merchant = $this->productRepository->findProductMerchant($productId, $merchantId);

        $merchantData = [
            'url' => $productData['url'],
            'price' => $productData['price'],
            'sale_price' => $productData['sale_price'],
            'is_available' => $productData['in_stock'],
            'last_price_check' => date('Y-m-d H:i:s'),
        ];

        if ($merchant) {
            $this->productRepository->updateProductMerchant($merchant->id, $merchantData);
        } else {
            $this->productRepository->createProductMerchant($productId, array_merge($merchantData, [
                'merchant_id' => $merchantId
            ]));
        }
    }

    protected function createProductFromFeed(array $productData, int $merchantId): void
    {
        // Create product with merchant data
        $product = $this->productService->createProduct([
            'name' => $productData['name'],
            'description' => $productData['description'],
            'price' => $productData['price'],
            'sale_price' => $productData['sale_price'],
            'sku' => $productData['sku'],
            'image' => $productData['image'],
            'is_active' => true,
            'in_stock' => $productData['in_stock'],
            'merchants' => [[
                'id' => $merchantId,
                'url' => $productData['url'],
                'price' => $productData['price'],
                'sale_price' => $productData['sale_price'],
                'is_available' => $productData['in_stock'],
            ]],
            'brand' => $productData['brand'] ?? ''
        ]);
    }

    public function getActiveFeedsForMerchant(int $merchantId): Collection
    {
        return $this->repository->getActiveFeedsByMerchant($merchantId);
    }

    public function getFeedsDueForFetch(): Collection
    {
        return $this->repository->getDueForFetch();
    }

    public function downloadFeedData(int $id): string
    {
        $feed = $this->repository->find($id);

        if (!$feed) {
            throw new Exception('Feed not found');
        }

        // Get all products for this merchant
        $products = $this->productRepository->getProductsByMerchant($feed->merchant_id);

        if ($products->isEmpty()) {
            throw new Exception('No products found for this merchant');
        }

        // Generate feed data based on type
        switch ($feed->feed_type) {
            case 'xml':
                return $this->generateXmlFeed($products);
            case 'csv':
                return $this->generateCsvFeed($products);
            case 'json':
                return $this->generateJsonFeed($products);
            default:
                throw new Exception('Unsupported feed type: ' . $feed->feed_type);
        }
    }

    protected function generateXmlFeed(Collection $products): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><products/>');

        foreach ($products as $product) {
            $productNode = $xml->addChild('product');
            $productNode->addChild('id', $product->id);
            $productNode->addChild('name', htmlspecialchars($product->name));
            $productNode->addChild('description', htmlspecialchars($product->description ?? ''));
            $productNode->addChild('sku', htmlspecialchars($product->sku ?? ''));
            $productNode->addChild('price', $product->price);

            if ($product->sale_price) {
                $productNode->addChild('sale_price', $product->sale_price);
            }

            $productNode->addChild('brand', htmlspecialchars($product->brand->name ?? ''));
            $productNode->addChild('category', htmlspecialchars($product->category->name ?? ''));
            $productNode->addChild('image', htmlspecialchars($product->main_image_url ?? ''));
            $productNode->addChild('url', url('/products/' . $product->slug));
            $productNode->addChild('in_stock', $product->stock_quantity > 0 ? 'true' : 'false');
            $productNode->addChild('stock_quantity', $product->stock_quantity ?? 0);

            // Add merchant-specific data if available
            if ($product->merchant_data) {
                $merchantNode = $productNode->addChild('merchant');
                $merchantNode->addChild('merchant_url', htmlspecialchars($product->merchant_data->url ?? ''));
                $merchantNode->addChild('merchant_price', $product->merchant_data->effective_price ?? $product->price);
                $merchantNode->addChild('merchant_sale_price', $product->merchant_data->effective_sale_price ?? '');
                $merchantNode->addChild('is_available', $product->merchant_data->is_available ? 'true' : 'false');
            }
        }

        return $xml->asXML();
    }

    protected function generateCsvFeed(Collection $products): string
    {
        $csv = [];

        // Header row
        $csv[] = [
            'id',
            'name',
            'description',
            'sku',
            'price',
            'sale_price',
            'brand',
            'category',
            'image',
            'url',
            'in_stock',
            'stock_quantity',
            'merchant_url',
            'merchant_price',
            'merchant_sale_price',
            'is_available'
        ];

        // Data rows
        foreach ($products as $product) {
            $csv[] = [
                $product->id,
                $product->name,
                $product->description ?? '',
                $product->sku ?? '',
                $product->price,
                $product->sale_price ?? '',
                $product->brand->name ?? '',
                $product->category->name ?? '',
                $product->main_image_url ?? '',
                url('/products/' . $product->slug),
                $product->stock_quantity > 0 ? 'true' : 'false',
                $product->stock_quantity ?? 0,
                $product->merchant_data->url ?? '',
                $product->merchant_data->effective_price ?? $product->price,
                $product->merchant_data->effective_sale_price ?? '',
                isset($product->merchant_data) && $product->merchant_data->is_available ? 'true' : 'false'
            ];
        }

        // Convert to CSV string
        $output = fopen('php://temp', 'r+');
        foreach ($csv as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csvString = stream_get_contents($output);
        fclose($output);

        return $csvString;
    }

    protected function generateJsonFeed(Collection $products): string
    {
        $productsArray = [];

        foreach ($products as $product) {
            $productData = [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description ?? '',
                'sku' => $product->sku ?? '',
                'price' => (float)$product->price,
                'sale_price' => $product->sale_price ? (float)$product->sale_price : null,
                'brand' => $product->brand->name ?? '',
                'category' => $product->category->name ?? '',
                'image' => $product->main_image_url ?? '',
                'url' => url('/products/' . $product->slug),
                'in_stock' => $product->stock_quantity > 0,
                'stock_quantity' => $product->stock_quantity ?? 0,
            ];

            // Add merchant-specific data if available
            if ($product->merchant_data) {
                $productData['merchant'] = [
                    'url' => $product->merchant_data->url ?: '',
                    'price' => (float)($product->merchant_data->effective_price ?: $product->price),
                    'sale_price' => $product->merchant_data->effective_sale_price ? (float)$product->merchant_data->effective_sale_price : null,
                    'is_available' => (bool)$product->merchant_data->is_available
                ];
            }

            $productsArray[] = $productData;
        }

        return json_encode([
            'products' => $productsArray,
            'total' => count($productsArray),
            'generated_at' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Create default mapper registry with all mappers
     *
     * @return FeedMapperRegistry
     */
    protected function createDefaultMapperRegistry(): FeedMapperRegistry
    {
        $registry = new FeedMapperRegistry();

        // Register specific mappers (order matters - more specific first)
        $registry->register(new AmazonFeedMapper());
        $registry->register(new EbayFeedMapper());
        // Add more mappers here as needed

        return $registry;
    }
}