<?php

namespace App\Controllers\Crm;

use App\Controllers\Controller;
use App\Enums\AttachmentableType;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Billing\AttachmentRepository;
use App\Services\Billing\AttachmentService;
use Exception;

class CrmAttachmentController extends Controller
{
    public function __construct(
        private readonly AttachmentService    $attachmentService,
        private readonly AttachmentRepository $attachmentRepository,
    ) {
        parent::__construct();
    }

    /**
     * GET crm/members/{memberId}/attachments
     * Optional query params: entity_type, entity_id
     */
    public function index(int $memberId, Request $request): JsonResponse
    {
        try {
            $siteId     = SiteContext::getId();
            $entityType = $request->query('entity_type');
            $entityId   = $request->query('entity_id');

            if ($entityType && $entityId) {
                $attachments = $this->attachmentRepository->findByEntity(
                    $entityType,
                    (int) $entityId,
                );
            } else {
                $attachments = $this->attachmentRepository->findByMember($memberId, $siteId);
            }

            return $this->resourceResponse(['attachments' => $attachments->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * POST crm/members/{memberId}/attachments  (multipart/form-data)
     *
     * Fields:
     *   file         binary   required
     *   entity_type  string   required  (AttachmentableType value)
     *   entity_id    int      required
     */
    public function store(int $memberId, Request $request): JsonResponse
    {
        try {
            $siteId = SiteContext::getId();

            $file = $request->file('file');
            if (!$file) {
                return $this->errorResponse('No file provided.', 422);
            }

            $entityTypeRaw = $request->input('entity_type');
            $entityId      = (int) $request->input('entity_id', 0);

            if (!$entityTypeRaw || !$entityId) {
                return $this->errorResponse('entity_type and entity_id are required.', 422);
            }

            $entityType = AttachmentableType::from($entityTypeRaw);
            $uploadedBy = Auth::id();

            $attachment = $this->attachmentService->upload(
                $file,
                $memberId,
                $siteId,
                $uploadedBy,
                $entityType,
                $entityId,
            );

            return $this->resourceResponse(['attachment' => $attachment->toArray()], 201);
        } catch (\ValueError $e) {
            return $this->errorResponse('Invalid entity_type value.', 422);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * DELETE crm/members/{memberId}/attachments/{id}
     */
    public function destroy(int $memberId, int $id): JsonResponse
    {
        try {
            $this->attachmentService->delete($id, $memberId);
            return $this->successResponse('Attachment deleted.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}