<?php

namespace App\Services\Cms\Pages;

use App\Database\Seeders\DTO\BatchParseResult;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Database\Database;
use App\Framework\Exceptions\BlockParserNotFoundException;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Framework\Support\Str;
use App\Framework\Validation\Validator;
use App\Models\Block;
use App\Models\Model;
use App\Parsers\BlockRegistry;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductSpecificationGroupRepository;
use App\Services\Cms\PersonService;
use App\Services\Product\ProductMatchingService;
use Exception;

class BlockParserService
{
    private BlockRegistry $blockRegistry;
    private Validator $validator;
    private BlockRepository $blockRepository;
    private PageRepository $pageRepository;
    private Database $database;
    private PersonService $personService;

    public function __construct(
        BlockRegistry   $blockRegistry,
        Validator       $validator,
        BlockRepository $blockRepository,
        PageRepository  $pageRepository,
        Database        $database
    )
    {
        $this->blockRegistry = $blockRegistry;
        $this->validator = $validator;
        $this->blockRepository = $blockRepository;
        $this->pageRepository = $pageRepository;
        $this->database = $database;
        $this->personService = new PersonService($database);
    }

    public function parseMultiplePages(array $pagesData): BatchParseResult
    {
        $results = [];
        $errors = [];
        $successCount = 0;
        $failedCount = 0;

        foreach ($pagesData as $pageIndex => $pageData) {
            try {
                $result = $this->parseSinglePage($pageData);
                $results[$pageIndex] = $result;
                $successCount++;

                $this->logSuccessfulParsing($pageIndex, $pageData);

            } catch (ValidationException $e) {
                $errors[$pageIndex] = $this->formatValidationError($e);
                $failedCount++;
                $this->logValidationFailure($pageIndex, $e);

            } catch (Exception $e) {
                $errors[$pageIndex] = $this->formatProcessingError($e);
                $failedCount++;
                $this->logProcessingFailure($pageIndex, $e);
            }
        }

        return new BatchParseResult($results, $errors, $successCount, $failedCount);
    }

    public function parsePageBlocks(int $pageId, array $blocks): array
    {
        return $this->database->transaction(function () use ($pageId, $blocks) {
            $results = [];
            $errors = [];

            foreach ($blocks as $index => $blockData) {

                try {
                    $result = $this->parseBlock($pageId, $blockData, $index);
                    $results[] = $result;
                } catch (ValidationException $e) {
                    $errors["block_{$index}"] = $e->getErrors();
                } catch (BlockParserNotFoundException $e) {
                    $errors["block_{$index}"] = ['type' => $e->getMessage()];
                }
            }

            if (!empty($errors)) {
                throw new ValidationException('Block validation failed', $errors);
            }

            return $results;
        });
    }

    public function parseBlock(int $pageId, array $blockData, int $order): Block
    {
        $this->validateBlockType($blockData);

        $type = $blockData['type'];
        $parser = $this->getParser($type);

        if (method_exists($parser, 'beforeValidation')) {
            $blockData = $parser->beforeValidation($blockData);
        }

        $this->performValidation($blockData, $parser);

        $parsedData = $parser->parse($blockData);

        // Handle product/deal block creation/matching
        if ($type === 'product' || $type === 'deal') {
            $parsedData = $this->handleProductBlock($parsedData, $type);
        }

        $result = $this->blockRepository->createBlock($pageId, $type, $parsedData, $order);

        if ($type === 'person' && !empty($parsedData['data']['email'])) {
            $this->personService->createOrUpdatePerson($parsedData['data']);
        }

        return $result;
    }

    /**
     * Handle product block creation - match or create product
     */
    private function handleProductBlock(array $parsedData, string $type = 'product'): array
    {
        // If user opted out of matching, don't do anything
        if (!empty($parsedData['opted_out_product_match'])) {
            return $parsedData;
        }

        // If product_id already provided (from search modal), keep it
        if (!empty($parsedData['product_id'])) {
            return $parsedData;
        }

        // Extract product name and brand based on block type
        if ($type === 'deal') {
            $productName = $parsedData['productName'] ?? null;
            $brand = $parsedData['brand'] ?? null;
        } else {
            $productName = $parsedData['productName'] ?? $parsedData['name'] ?? null;
            $brand = $parsedData['brand'] ?? null;
        }

        if (empty($productName)) {
            return $parsedData;
        }

        $matchingService = new ProductMatchingService(new ProductRepository(new ProductSpecificationGroupRepository()));
        $matches = $matchingService->findMatches($productName, $brand, SiteContext::getId());

        // If we have a high confidence match (>85%), use it
        if (!empty($matches) && $matches[0]['similarity'] > 0.85) {
            $parsedData['product_id'] = $matches[0]['product']->id;
        } else {
            // Create new product if no good match
            $parsedData['product_id'] = $this->createProductFromBlock($parsedData, $type);
        }

        return $parsedData;
    }

    /**
     * Create a new product from block data
     */
    private function createProductFromBlock(array $blockData, string $type = 'product'): int
    {
        $productRepository = new \App\Repositories\Product\ProductRepository(new ProductSpecificationGroupRepository());

        $brand = $blockData['brand'] ?? null;
        $price = $blockData['price'] ?? 0;
        $salePrice = $blockData['salePrice'] ?? 0;
        $description = $blockData['description'] ?? null;
        $image = $blockData['image']['src'] ?? null;

        if ($type === 'deal') {
            $name = $blockData['productName'] ?? $blockData['title'];
        } else {
            $name = $blockData['productName'] ?? $blockData['name'];
        }

        $productData = [
            'name' => $name,
            'brand' => $brand,
            'price' => $price,
            'sale_price' => $salePrice,
            'description' => $description,
            'image' => $image,
            'site_id' => SiteContext::getId(),
            'is_active' => true,
            'slug' => Str::slug($name),
        ];

        $product = $productRepository->create($productData);

        return $product->id;
    }

    public function buildBlock(
        int   $pageId,
        array $blockData,
        int   $order,
        bool  $isPreviewMode = false,
        ?int  $siteId = null
    ): string
    {
        $this->validateBlockType($blockData);

        $type = $blockData['type'];
        $parser = $this->getParser($type);

        if (method_exists($parser, 'beforeValidation')) {
            $blockData = $parser->beforeValidation($blockData);
        }

        if (!$isPreviewMode) {
            $this->performValidation($blockData, $parser);
        }

        if (!empty($blockData['subscribersOnly']) && $blockData['subscribersOnly'] === true) {
            if (!MemberAuth::check()) {
                return ''; // Return empty string if not logged in
            }
        }

        $parsedData = $parser->parse($blockData);

        return $parser->generateHtml($parsedData, $pageId, $siteId);
    }

    public function updateBlock(int $blockId, array $blockData): Model
    {
        $block = $this->blockRepository->find($blockId);

        if (!$block) {
            throw new Exception("Block not found");
        }

        $type = $blockData['type'] ?? $block->type;
        $parser = $this->getParser($type);

        $this->performValidation($blockData, $parser);

        $parsedData = $parser->parse($blockData);

        return $this->blockRepository->update($blockId, [
            'type' => $type,
            'data' => $parsedData
        ]);
    }

    public function replacePageBlocks(int $pageId, array $blocksData): array
    {
        return $this->database->transaction(function () use ($pageId, $blocksData) {
            $this->blockRepository->deletePageBlocks($pageId);
            return $this->parsePageBlocks($pageId, $blocksData);
        });
    }

    private function parseSinglePage(array $pageData): array
    {
        return $this->database->transaction(function () use ($pageData) {
            $pageInfo = $pageData['page'] ?? [];
            $blocksData = $pageData['blocks'] ?? [];

            $page = $this->pageRepository->create($pageInfo);
            $blocks = $this->parsePageBlocks($page->id, $blocksData);

            return [
                'page' => $page->toArray(),
                'blocks' => array_map(fn($block) => $block->toArray(), $blocks),
                'status' => 'success'
            ];
        });
    }

    private function validateBlockType(array $blockData): void
    {
        $type = $blockData['type'] ?? null;

        if (!$type) {
            throw new ValidationException(
                'Block type is required'
            );
        }
    }

    public function getParser($type)
    {
        $parser = $this->blockRegistry->getParser($type);

        if (!$parser) {
            throw new BlockParserNotFoundException($type);
        }

        return $parser;
    }

    private function validateBlockData(array $blockData, $parser): void
    {
        $validationResult = $this->validator->validate($blockData, $parser->getValidationRules());

        if (!$validationResult->isValid()) {
            throw new ValidationException('Failed to validate block data', $validationResult->getErrors());
        }
    }

    private function formatValidationError(ValidationException $e): array
    {
        return [
            'type' => 'validation_error',
            'message' => 'Page validation failed',
            'errors' => $e->getErrors()
        ];
    }

    private function formatProcessingError(Exception $e): array
    {
        return [
            'type' => 'processing_error',
            'message' => $e->getMessage()
        ];
    }

    private function logSuccessfulParsing(int $pageIndex, array $pageData): void
    {
        Logger::info("Successfully parsed page", [
            'page_index' => $pageIndex,
            'page_title' => $pageData['page']['title'] ?? 'Unknown'
        ]);
    }

    private function logValidationFailure(int $pageIndex, ValidationException $e): void
    {
        Logger::error("Page validation failed", [
            'page_index' => $pageIndex,
            'errors' => $e->getErrors()
        ]);
    }

    private function logProcessingFailure(int $pageIndex, Exception $e): void
    {
        Logger::error("Page processing failed", [
            'page_index' => $pageIndex,
            'error' => $e->getMessage()
        ]);
    }

    /**
     * Validate block data without saving (useful for gallery slides)
     */
    public function validateBlock(array $blockData): void
    {
        $this->validateBlockType($blockData);

        $type = $blockData['type'];
        $parser = $this->getParser($type);

        if (method_exists($parser, 'beforeValidation')) {
            $blockData = $parser->beforeValidation($blockData);
        }

        $this->performValidation($blockData, $parser);
    }

    /**
     * Internal validation method used by both parseBlock and validateBlock
     */
    private function performValidation(array $blockData, $parser): void
    {
        $validationResult = $this->validator->validate($blockData, $parser->getValidationRules());

        if (!$validationResult->isValid()) {

            throw new ValidationException('Failed to validate block data', $validationResult->getErrors());
        }
    }
}