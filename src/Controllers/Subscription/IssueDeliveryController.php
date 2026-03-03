<?php

namespace App\Controllers\Subscription;

use App\Actions\Subscriptions\ExportIssueSchedulesAction;
use App\Actions\Subscriptions\ImportIssueSchedulesAction;
use App\Controllers\Controller;
use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Framework\Support\SiteContext;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Resources\IssueDeliveryResource;
use App\Search\SearchCriteriaParser;
use App\Services\Subscriptions\IssueDeliveryService;
use Exception;

class IssueDeliveryController extends Controller
{
    public function __construct(
        private readonly IssueDeliveryService    $issueDeliveryService,
        private readonly IssueDeliveryRepository    $issueDeliveryRepository,
        private readonly ImportIssueSchedulesAction $importIssueSchedulesAction,
        private readonly ExportIssueSchedulesAction $exportIssueSchedulesAction
    )
    {
        parent::__construct();
    }

    public function index(Request $request, string $siteName): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $siteName);
            $result = $this->issueDeliveryRepository->search($criteria);

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
            'data' => $schedule
        ]);
    }

    public function store(Request $request)
    {
        $siteId = SiteContext::getId();

        $data = $request->all();
        $data['site_id'] = $siteId;

        if (isset($data['title'])) {
            $data['issue_title'] = $data['title'];
        }

        try {
            if (empty($data['issue_title']) || empty($data['issue_number']) || empty($data['on_sale_date'])) {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => 'Missing required fields'
                ], 422);
            }

            $schedule = $this->issueDeliveryRepository->create($data);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Schedule created successfully',
                'data' => $schedule->toArray()
            ]);

        } catch (\Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        $data = $request->all();

        if (isset($data['title'])) {
            $data['issue_title'] = $data['title'];
        }

        try {
            $schedule = $this->issueDeliveryRepository->update($id, $data);

            if (isset($data['plan_ids'])) {
                $schedule->subscriptionPlans()->sync($data['plan_ids']);
            }

            $schedule = $this->issueDeliveryRepository->find($id);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Schedule updated successfully',
                'data' => $schedule->toArray()
            ]);

        } catch (\Exception $e) {

            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(int $id)
    {
        try {
            $this->issueDeliveryRepository->delete($id);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Schedule deleted successfully'
            ]);

        } catch (\Exception $e) {

            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(Request $request, int $id)
    {
        // 'status' => 'required|string|in:draft,active,cancelled'
        $data = $request->all();

        try {
            $status = IssueScheduleStatus::from($data['status']);
            $schedule = $this->issueDeliveryService->updateScheduleStatus($id, $status);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Status updated successfully',
                'data' => $schedule
            ]);

        } catch (\Exception $e) {

            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function importCsv(Request $request)
    {
        $siteId = SiteContext::getId();

        if (!$request->hasFile('csv_file')) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'No CSV file uploaded'
            ], 400);
        }

        $file = $request->file('csv_file');

        try {
            $result = $this->importIssueSchedulesAction->execute($siteId, $file['tmp_name']);

            return $this->resourceResponse([
                'success' => true,
                'message' => "Imported {$result['success_count']} schedules successfully",
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function exportCsv()
    {
        $siteId = SiteContext::getId();

        try {
            $filepath = $this->exportIssueSchedulesAction->execute($siteId);

            return $this->downloadFile($filepath, basename($filepath));

        } catch (\Exception $e) {

            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

}