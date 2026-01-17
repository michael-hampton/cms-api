<?php

namespace App\Controllers;

use App\Actions\Brief\ConvertBriefToPage;
use App\Framework\Container;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Repositories\Cms\BriefRepository;
use App\Search\SearchCriteriaParser;
use Exception;

class BriefController extends Controller
{
    public function __construct(
        private readonly BriefRepository $briefRepository
    )
    {
        parent::__construct();
    }

    public function index(Request $request, string $siteName): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $siteName);
            $result = $this->briefRepository->search($criteria);

            return $this->searchResponse($result);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show(int $id, string $siteName): JsonResponse
    {
        try {
            $brief = $this->briefRepository->getCompleteBriefData($id);

            if (!$brief) {
                return $this->errorResponse('Brief not found', 404);
            }

            return $this->resourceResponse(['data' => $brief->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();
            $data['site_id'] = $request->get('site_id');

            $brief = $this->briefRepository->create($data);

            return $this->resourceResponse(['data' => $brief->toArray()], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();
            $brief = $this->briefRepository->update($id, $data);

            if (!$brief) {
                return $this->errorResponse('Brief not found', 404);
            }

            return $this->resourceResponse(['data' => $brief->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function destroy(int $id, string $siteName): JsonResponse
    {
        try {
            $result = $this->briefRepository->delete($id);

            if (!$result) {
                return $this->errorResponse('Brief not found', 404);
            }

            return $this->successResponse('Brief deleted successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function addAttachment(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();
            $attachment = $this->briefRepository->addAttachment($id, $data);

            return $this->resourceResponse(['data' => $attachment->toArray()], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function deleteAttachment(int $id, int $attachmentId, string $siteName): JsonResponse
    {
        try {
            $result = $this->briefRepository->deleteAttachment($id, $attachmentId);

            if (!$result) {
                return $this->errorResponse('Attachment not found', 404);
            }

            return $this->successResponse('Attachment deleted successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function addComment(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();
            $data['user_id'] = $request->get('user_id'); // Should come from auth

            $comment = $this->briefRepository->addComment($id, $data);

            return $this->resourceResponse(['data' => $comment->toArray()], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function deleteComment(int $id, int $commentId, string $siteName): JsonResponse
    {
        try {
            $result = $this->briefRepository->deleteComment($id, $commentId);

            if (!$result) {
                return $this->errorResponse('Comment not found', 404);
            }

            return $this->successResponse('Comment deleted successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function convertToPage(int $id, Request $request, string $siteName): JsonResponse
    {
        /** @var ConvertBriefToPage $handler */
        $handler = Container::getInstance()->make(ConvertBriefToPage::class);

        try {
            $data = $request->all();
            $result = $handler->handle($id, $data);

            return $this->resourceResponse(['data' => $result]);
        } catch (ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $e->getErrors()
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function archive(int $id, string $siteName): JsonResponse
    {
        try {
            $result = $this->briefRepository->archive($id);

            if (!$result) {
                return $this->errorResponse('Brief not found', 404);
            }

            return $this->successResponse('Brief archived successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateComment(int $id, int $commentId, Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();
            $comment = $this->briefRepository->updateComment($id, $commentId, $data['content']);

            if (!$comment) {
                return $this->errorResponse('Comment not found', 404);
            }

            return $this->resourceResponse(['data' => $comment->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateAttachment(int $id, int $attachmentId, Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();
            $attachment = $this->briefRepository->updateAttachment($id, $attachmentId, $data);

            if (!$attachment) {
                return $this->errorResponse('Attachment not found', 404);
            }

            return $this->resourceResponse(['data' => $attachment->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}