<?php

namespace App\Controllers;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\Request;
use App\Framework\Http\JsonResponse;
use App\Repositories\ImageRepository;
use App\Requests\CreateImageCategoryRequest;
use App\Requests\UpdateImageRequest;
use App\Search\SearchCriteriaParser;
use App\Services\ImageService;
use Exception;

class ImageController extends Controller
{
    private ImageService $imageService;

    public function __construct(ImageService $imageService, private readonly ImageRepository $imageRepository)
    {
        $this->imageService = $imageService;
        parent::__construct();
    }

    public function index(Request $request, string $siteName): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $siteName);;
            $result = $this->imageRepository->search($criteria);

            return $this->searchResponse($result);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(Request $request, string $siteName): JsonResponse
    {
        try {
            $file = $request->file('image');

            if (!$file) {
                return $this->errorResponse('No image file provided', 400);
            }

            $metadata = [
                'name' => $request->get('name'),
                'alt_text' => $request->get('alt_text'),
                'caption' => $request->get('caption'),
                'description' => $request->get('description'),
                'categories' => $request->get('categories', []),
                'site_id' => $request->get('site_id'),
                'tags' => $request->get('tags', [])
            ];

            $image = $this->imageService->uploadImage($file, $metadata);

            return $this->jsonResponse([
                'image' => $image->toArrayWithUsage(),
                'message' => 'Image uploaded successfully'
            ], 201);

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

    public function show(int $id): JsonResponse
    {
        try {
            $image = $this->imageService->getImage($id);

            if (!$image) {
                return $this->errorResponse('Image not found', 404);
            }

            return $this->jsonResponse([
                'image' => $image
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(int $id, UpdateImageRequest $request, string $siteName): JsonResponse
    {
        try {
            $metadata = [
                'name' => $request->get('name'),
                'alt_text' => $request->get('alt_text'),
                'caption' => $request->get('caption'),
                'description' => $request->get('description'),
                'categories' => $request->get('categories', []),
                'tags' => $request->get('tags')
            ];

            // Remove null values
            $metadata = array_filter($metadata, function($value) {
                return $value !== null;
            });

            $image = $this->imageService->updateImageMetadata($id, $metadata);

            return $this->jsonResponse([
                'image' => $image,
                'message' => 'Image updated successfully'
            ]);

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

    public function destroy(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $hardDelete = $request->get('hard_delete', false);

            $result = $this->imageService->deleteImage($id, $hardDelete);

            if (!$result) {
                return $this->errorResponse('Failed to delete image', 500);
            }

            $message = $hardDelete ? 'Image permanently deleted' : 'Image moved to trash';
            return $this->successResponse($message);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function bulkDestroy(Request $request, string $siteName): JsonResponse
    {
        try {
            $imageIds = $request->get('image_ids', []);
            $hardDelete = $request->get('hard_delete', false);

            if (empty($imageIds)) {
                return $this->errorResponse('No image IDs provided', 400);
            }

            $results = $this->imageService->bulkDeleteImages($imageIds, $hardDelete);

            return $this->jsonResponse([
                'results' => $results,
                'message' => "Processed {$results['deleted']} images, {$results['failed']} failed"
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function recent(Request $request, string $siteName): JsonResponse
    {
        try {
            $limit = (int)$request->get('limit', 10);
            $images = $this->imageService->getRecentImages($limit);

            return $this->jsonResponse([
                'images' => $images->map(function($image) {
                    return $image->toArrayWithUsage();
                })
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function statistics(): JsonResponse
    {
        try {
            $stats = $this->imageService->getImageStatistics();
            return $this->jsonResponse(['statistics' => $stats]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function unused(Request $request, string $siteName): JsonResponse
    {
        try {
            $olderThanDays = $request->get('older_than_days');
            $images = $this->imageService->getUnusedImages($olderThanDays);

            return $this->jsonResponse([
                'images' => $images->map(function($image) {
                    return $image->toArrayWithUsage();
                }),
                'count' => $images->count()
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function cleanup(Request $request, string $siteName): JsonResponse
    {
        try {
            $olderThanDays = (int)$request->get('older_than_days', 30);
            $results = $this->imageService->cleanupUnusedImages($olderThanDays);

            return $this->jsonResponse([
                'results' => $results,
                'message' => "Cleaned up {$results['deleted']} unused images, freed " .
                    $this->formatBytes($results['freed_space'])
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function trackUsage(Request $request, string $siteName): JsonResponse
    {
        try {
            $imageId = (int)$request->get('image_id');
            $usableType = $request->get('usable_type');
            $usableId = (int)$request->get('usable_id');
            $context = $request->get('context');

            if (!$imageId || !$usableType || !$usableId) {
                return $this->errorResponse('Missing required parameters', 400);
            }

            $this->imageService->trackImageUsage($imageId, $usableType, $usableId, $context);

            return $this->successResponse('Image usage tracked successfully');

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function removeUsage(Request $request, string $siteName): JsonResponse
    {
        try {
            $imageId = (int)$request->get('image_id');
            $usableType = $request->get('usable_type');
            $usableId = (int)$request->get('usable_id');
            $context = $request->get('context');

            if (!$imageId || !$usableType || !$usableId) {
                return $this->errorResponse('Missing required parameters', 400);
            }

            $this->imageService->removeImageUsage($imageId, $usableType, $usableId, $context);

            return $this->successResponse('Image usage removed successfully');

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function categories(string $siteName): JsonResponse
    {
        try {
            $categories = $this->imageService->getCategories($siteName);

            return $this->jsonResponse([
                'categories' => $categories->toArray()
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function createCategory(CreateImageCategoryRequest $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->validated();

            $category = $this->imageService->createCategory($data);

            return $this->jsonResponse([
                'category' => $category,
                'message' => 'Category created successfully'
            ], 201);

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

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor(log($bytes, 1024));

        return sprintf("%.1f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }

    public function duplicate(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $metadata = [
                'name' => $request->get('name'),
                'alt_text' => $request->get('alt_text'),
                'caption' => $request->get('caption'),
                'description' => $request->get('description'),
                'original_name' => $request->get('original_name'),
                'categories' => $request->get('categories', []),
                'site_id' => $request->get('site_id'),
                'tags' => $request->get('tags')
            ];

            // Remove null values
            $metadata = array_filter($metadata, function($value) {
                return $value !== null;
            });

            $newImage = $this->imageService->duplicateImage($id, $metadata);

            return $this->jsonResponse([
                'image' => $newImage->toArrayWithUsage(),
                'message' => 'Image duplicated successfully'
            ], 201);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}