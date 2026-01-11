<?php

namespace App\Controllers;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Repositories\Cms\BlockRepository;
use App\Services\Cms\BlockParserService;
use Exception;

class BlockController extends Controller
{
    private $blockParserService;
    private $blockRepository;

    public function __construct(BlockParserService $blockParserService, BlockRepository $blockRepository)
    {
        $this->blockParserService = $blockParserService;
        $this->blockRepository = $blockRepository;
    }

    public function show(int $id): array
    {
        try {
            $block = $this->blockRepository->find($id);

            if (!$block) {
                return $this->errorResponse('Block not found', 404);
            }

            $blockArray = $block->toArray();
            $blockArray['data'] = json_decode($blockArray['data'], true);

            return $this->jsonResponse(['block' => $blockArray]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(int $id, JsonResponse $request): JsonResponse
    {
        try {
            $block = $this->blockParserService->updateBlock($id, $request);

            $blockArray = $block->toArray();
            $blockArray['data'] = json_decode($blockArray['data'], true);

            return $this->jsonResponse(['block' => $blockArray]);
        } catch (ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $e->getValidationResult()->getErrors()
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->blockRepository->delete($id);

            if (!$result) {
                return $this->errorResponse('Block not found', 404);
            }

            return $this->successResponse('Block deleted successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getByType(string $type): JsonResponse
    {
        try {
            $blocks = $this->blockRepository->getBlocksByType($type);

            $blockArrays = array_map(function($block) {
                $blockArray = $block->toArray();
                $blockArray['data'] = json_decode($blockArray['data'], true);
                return $blockArray;
            }, $blocks);

            return $this->jsonResponse(['blocks' => $blockArrays]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}