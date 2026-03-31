<?php

namespace App\Controllers\Subscription;

use App\Actions\SubscriptionPlan\BulkTogglePlanActive;
use App\Controllers\Controller;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Framework\Resource\ResourceCollection;
use App\Framework\Support\SiteContext;
use App\Models\Subscription;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Requests\BulkToggleActiveRequest;
use App\Requests\Subscription\CreateSubscriptionPlanRequest;
use App\Requests\Subscription\UpdateSubscriptionPlanRequest;
use App\Resources\PaymentResource;
use App\Resources\SubscriptionPlanResource;
use App\Resources\SubscriptionResource;
use App\Search\SearchCriteriaParser;
use App\Services\Subscriptions\SubscriptionPlanService;
use Exception;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionRepository     $subscriptionRepository,
        private readonly PaymentRepository          $paymentRepository,
        private readonly SubscriptionPlanRepository $subscriptionPlanRepository,
        private readonly SubscriptionPlanService $planService,
        private readonly BulkTogglePlanActive    $bulkTogglePlanActive
    )
    {
    }

    public function index()
    {
        try {
            $subscriptions = Subscription::with(['member', 'plan'])->orderBy('created_at', 'desc')->paginate(10);

            $collection = new ResourceCollection($subscriptions['data'], SubscriptionResource::class);

            return $this->resourceResponse($collection->toArray());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function payments(Request $request, string $siteName)
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $siteName);
            $result = $this->paymentRepository->search($criteria);

            $collection = new PaginatedResourceCollection($result, PaymentResource::class);

            return $this->resourceResponse($collection->toArray());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function plans(Request $request, string $siteName)
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $siteName);

            // Eager-load regionSets at the query level so the resource can read them
            $result = $this->subscriptionPlanRepository->search($criteria);

            $collection = new PaginatedResourceCollection($result, SubscriptionPlanResource::class);

            return $this->resourceResponse($collection->toArray());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function createPlan(CreateSubscriptionPlanRequest $request)
    {
        $siteId = SiteContext::getId();

        try {
            $data = $request->validated();
            $data['site_id'] = $siteId;

            // Handle image uploads
            if ($request->hasFile('print_image')) {
                $upload = new \App\Framework\FileUpload\ImageUpload(
                    $request->file('print_image')->getFileInfo(),
                    'uploads/plans'
                );
                $path = $upload->store('print');
                $data['print_image_url'] = $path;
            }

            if ($request->hasFile('digital_image')) {
                $upload = new \App\Framework\FileUpload\ImageUpload(
                    $request->file('digital_image')->getFileInfo(),
                    'uploads/plans'
                );
                $path = $upload->store('digital');
                $data['digital_image_url'] = $path;
            }

            $plan = $this->planService->createPlan($data, $siteId);

            $plan->load(['regionSets']);

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Plan created successfully',
                'plan' => $plan
            ]);

        } catch (ValidationException $validationException) {
            return $this->errorResponse('Validation failed', 422, $validationException->getErrors());
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updatePlan(UpdateSubscriptionPlanRequest $request, int $id)
    {
        try {
            $siteId = SiteContext::getId();
            $data = $request->validated();

            if ($request->hasFile('print_image')) {
                $upload = new \App\Framework\FileUpload\ImageUpload(
                    $request->file('print_image')->getFileInfo(),
                    'uploads/plans'
                );
                $path = $upload->store('print');
                $data['print_image_url'] = $path;
            }

            if ($request->hasFile('digital_image')) {
                $upload = new \App\Framework\FileUpload\ImageUpload(
                    $request->file('digital_image')->getFileInfo(),
                    'uploads/plans'
                );
                $path = $upload->store('digital');
                $data['digital_image_url'] = $path;
            }

            $plan = $this->planService->updatePlan($id, $data, $siteId);

            if (!$plan) {
                return $this->jsonResponse(['success' => false, 'message' => 'Plan not found'], 404);
            }

            $plan->load(['regionSets']);

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Plan updated successfully',
                'plan' => $plan
            ]);
        } catch (ValidationException $validationException) {
            return $this->errorResponse('Validation failed', 422, $validationException->getErrors());

        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deletePlan(Request $request, int $id)
    {
        try {
            $success = $this->planService->deletePlan($id);

            if (!$success) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to delete plan'
                ], 500);
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Plan deleted successfully'
            ]);


        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function bulkToggleActive(BulkToggleActiveRequest $request)
    {
        try {
            $planIds = $request->get('plan_ids', []);
            $active = (bool)$request->get('active');

            if (empty($planIds) || !is_array($planIds)) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'plan_ids must be a non-empty array',
                ], 422);
            }

            $result = $this->bulkTogglePlanActive->handle($planIds, $active);

            return $this->jsonResponse([
                'success' => true,
                'message' => sprintf(
                    '%d plan(s) %s, %d failed',
                    count($result['updated']),
                    $active ? 'activated' : 'deactivated',
                    count($result['failed'])
                ),
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}