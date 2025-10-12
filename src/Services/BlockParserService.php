<?php

namespace App\Services;

use App\DTO\BatchParseResult;
use App\Framework\Database\Database;
use App\Framework\Exceptions\BlockParserNotFoundException;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Support\Logger;
use App\Framework\Validation\Validator;
use App\Models\Block;
use App\Parsers\BlockRegistry;
use App\Repositories\BlockRepository;
use App\Repositories\PageRepository;
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

        $this->validateBlockData($blockData, $parser);

        $parsedData = $parser->parse($blockData);

        $result = $this->blockRepository->createBlock($pageId, $type, $parsedData, $order);

        if ($type === 'person' && !empty($parsedData['data']['email'])) {
            $this->personService->createOrUpdatePerson($parsedData['data']);
        }

        return $result;
    }

    public function buildBlock(int $pageId, array $blockData, int $order, bool $isPreviewMode = false): string
    {
        $this->validateBlockType($blockData);

        $type = $blockData['type'];
        $parser = $this->getParser($type);

        if (method_exists($parser, 'beforeValidation')) {
            $blockData = $parser->beforeValidation($blockData);
        }

        if (!$isPreviewMode) {
            $this->validateBlockData($blockData, $parser);
        }

        $parsedData = $parser->parse($blockData);

        return $parser->generateHtml($parsedData, $pageId);
    }

    public function updateBlock(int $blockId, array $blockData): Block
    {
        $block = $this->blockRepository->find($blockId);

        if (!$block) {
            throw new Exception("Block not found");
        }

        $type = $blockData['type'] ?? $block->type;
        $parser = $this->getParser($type);

        $this->validateBlockData($blockData, $parser);

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

    private function getParser($type)
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
            throw new ValidationException($validationResult);
        }
    }

//    private function executeInTransaction(callable $callback)
//    {
//        $this->database->beginTransaction();
//
//        try {
//            $result = $callback();
//            $this->database->commit();
//            return $result;
//        } catch (Exception $e) {
//            $this->database->rollBack();
//            throw $e;
//        }
//    }

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
}