<?php

namespace App\Controllers\Subscription;

use App\Actions\Subscriptions\ExportIssueSchedulesAction;
use App\Actions\Subscriptions\ImportIssueSchedulesAction;
use App\Controllers\Controller;
use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Framework\Support\SiteContext;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Requests\StoreIssueDeliveryRequest;
use App\Requests\Subscription\UpdateIssueDeliveryRequest;
use App\Resources\IssueDeliveryResource;
use App\Search\SearchCriteriaParser;
use App\Services\Subscriptions\IssueDeliveryService;
use Exception;

class IssueDeliveryController extends Controller
{
    public function __construct(
        private readonly IssueDeliveryService       $issueDeliveryService,
        private readonly IssueDeliveryRepository    $issueDeliveryRepository,
        private readonly ImportIssueSchedulesAction $importIssueSchedulesAction,
        private readonly ExportIssueSchedulesAction $exportIssueSchedulesAction,
    ) {
        parent::__construct();
    }

    public function index(Request $request, string $site): JsonResponse
    {
        try {
            $criteria   = SearchCriteriaParser::fromRequest($request, $site);
            $result     = $this->issueDeliveryRepository->search($criteria);
            $collection = new PaginatedResourceCollection($result, IssueDeliveryResource::class);

            return $this->resourceResponse($collection->toArray());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show(int $id)
    {
        $schedule = $this->issueDeliveryRepository->find($id);

        if (!$schedule) {
            return $this->notFound('Schedule not found');
        }

        return $this->resourceResponse([
            'success' => true,
            'data'    => $schedule,
        ]);
    }

    public function store(StoreIssueDeliveryRequest $request)
    {
        try {
            $siteId = SiteContext::getId();
            $data   = $request->validated();
            $data['promotion_id'] = $data['promotion_id'] ?: null;

            $data['site_id'] = $siteId;

            if (isset($data['title'])) {
                $data['issue_title'] = $data['title'];
            }

            // Handle optional cover image upload
            if ($request->hasFile('cover_image')) {
                $uploadPath = $this->issueDeliveryService->storeCoverImage(
                    $request->file('cover_image')
                );

                $data['cover_image'] = url($uploadPath);
            }

            $schedule = $this->issueDeliveryRepository->create($data);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Schedule created successfully',
                'data'    => $schedule->toArray(),
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateIssueDeliveryRequest $request, int $id)
    {
        try {
            $data = $request->validated();
            $data['promotion_id'] = $data['promotion_id'] ?: null;

            if (isset($data['title'])) {
                $data['issue_title'] = $data['title'];
            }

            // Handle cover image replacement / removal
            if ($request->hasFile('cover_image')) {
                $existing = $this->issueDeliveryRepository->find($id);

                $coverImage = $this->issueDeliveryService->replaceCoverImage(
                    $existing,
                    $request->file('cover_image')
                );

                $data['cover_image'] = url($coverImage);
            } elseif (array_key_exists('cover_image', $data) && $data['cover_image'] === null) {
                // Explicit null means the client wants to remove the cover image
                $existing = $this->issueDeliveryRepository->find($id);
                if ($existing) {
                    $this->issueDeliveryService->removeCoverImage($existing);
                }
                $data['cover_image'] = null;
            }

            $schedule = $this->issueDeliveryRepository->update($id, $data);

            if (isset($data['plan_ids'])) {
                $schedule->subscriptionPlans()->sync($data['plan_ids']);
            }

            $schedule = $this->issueDeliveryRepository->find($id);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Schedule updated successfully',
                'data'    => $schedule->toArray(),
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(int $id)
    {
        try {
            // Remove the cover image from disk before deleting the record
            $schedule = $this->issueDeliveryRepository->find($id);
            if ($schedule) {
                $this->issueDeliveryService->removeCoverImage($schedule);
            }

            $this->issueDeliveryRepository->delete($id);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Schedule deleted successfully',
            ]);
        } catch (Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateStatus(Request $request, int $id)
    {
        $data = $request->all();

        try {
            $status = IssueScheduleStatus::tryFrom($data['status']);

            if (!$status) {
                return $this->errorResponse('Invalid status', 500);
            }

            $schedule = $this->issueDeliveryService->updateScheduleStatus($id, $status);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Status updated successfully',
                'data'    => $schedule,
            ]);
        } catch (Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function importCsv(Request $request)
    {
        $siteId = SiteContext::getId();

        if (!$request->hasFile('csv_file')) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'No CSV file uploaded',
            ], 400);
        }

        $file = $request->file('csv_file');

        try {
            $result = $this->importIssueSchedulesAction->execute($siteId, $file['tmp_name']);

            return $this->resourceResponse([
                'success' => true,
                'message' => "Imported {$result['success_count']} schedules successfully",
                'data'    => $result,
            ]);
        } catch (Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function exportCsv()
    {
        $siteId = SiteContext::getId();

        try {
            $filepath = $this->exportIssueSchedulesAction->execute($siteId);

            return $this->downloadFile($filepath, basename($filepath));
        } catch (Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}