<?php

namespace App\Controllers\Cms;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Requests\CreateVideoRequest;
use App\Services\Cms\VideoService;

class VideoController extends Controller
{
    private VideoService $videoService;

    public function __construct(VideoService $videoService)
    {
        $this->videoService = $videoService;
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'query' => $request->input('q'),
            'page' => $request->input('page', 1),
            'per_page' => $request->input('per_page', 20),
            'sort_by' => $request->input('sort_by', 'created_at'),
            'sort_order' => $request->input('sort_order', 'desc')
        ];

        $result = $this->videoService->getVideos($filters);

        return $this->resourceResponse([
            'items' => $result->getData(),
            'pagination' => [
                'total' => $result->getTotal(),
                'page' => $result->getPage(),
                'per_page' => $result->getPerPage(),
                'total_pages' => $result->getTotalPages(),
                'has_more' => $result->hasMore()
            ]
        ]);
    }

    public function upload(CreateVideoRequest $request): JsonResponse
    {
        try {
            $file = $request->file('video');

            if (!$file || !$file->isValid()) {
                return $this->jsonResponse([
                    'error' => 'No valid video file provided'
                ], 400);
            }

            $metadata = $request->validated();

            $video = $this->videoService->uploadVideo($file, $metadata);

            return $this->jsonResponse([
                'id' => $video->id,
                'name' => $video->original_name,
                'url' => $video->url,
                'thumbnails' => $video->getThumbnails(),
                'size' => $video->file_size,
                'duration' => $video->duration,
                'uploadDate' => $video->created_at
            ], 201);

        } catch (\Exception $e) {
            return $this->jsonResponse([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $video = $this->videoService->getVideo($id);

        if (!$video) {
            return $this->jsonResponse(['error' => 'Video not found'], 404);
        }

        return $this->jsonResponse([
            'id' => $video->id,
            'name' => $video->original_name,
            'url' => $video->url,
            'thumbnails' => $video->getThumbnails(),
            'size' => $video->file_size,
            'duration' => $video->duration,
            'width' => $video->width,
            'height' => $video->height,
            'title' => $video->title,
            'description' => $video->description,
            'uploadDate' => $video->created_at
        ]);
    }

    public function delete(Request $request, int $id): JsonResponse
    {
        try {
            $hardDelete = $request->input('hard_delete', false);
            $this->videoService->deleteVideo($id, $hardDelete);

            return $this->jsonResponse(['message' => 'Video deleted successfully']);

        } catch (\Exception $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 400);
        }
    }
}