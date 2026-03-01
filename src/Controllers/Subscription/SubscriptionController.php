<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Framework\Resource\ResourceCollection;
use App\Framework\Support\SiteContext;
use App\Models\Subscription;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Resources\SubscriptionResource;
use App\Services\Subscriptions\SubscriptionPlanService;
use Exception;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionRepository     $subscriptionRepository,
        private readonly PaymentRepository          $paymentRepository,
        private readonly SubscriptionPlanRepository $subscriptionPlanRepository,
        private readonly SubscriptionPlanService    $planService
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

    public function payments()
    {
        try {
            $result = $this->paymentRepository->getAllPayments();
            return $this->resourceResponse(['payments' => $result->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'subscription_id' => $payment->subscription_id,
                    'order_id' => $payment->order_id,
                    'site_id' => $payment->site_id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                    'payment_method' => $payment->payment_method,
                    'transaction_id' => $payment->transaction_id,
                    'payment_intent_id' => $payment->payment_intent_id,
                    'error_message' => $payment->error_message,
                    'error_data' => $payment->error_data,
                    'paid_at' => $payment->paid_at->format('Y-m-d H:i:s'),
                    'created_at' => $payment->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $payment->updated_at
                ];
            })]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function plans()
    {
        try {
            $plans = $this->subscriptionPlanRepository->getActivePlans(SiteContext::getId());
            return $this->resourceResponse(['plans' => $plans]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function createPlan(Request $request)
    {
        $siteId = SiteContext::getId();

        try {
            $plan = $this->planService->createPlan($request->all(), $siteId);

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Plan created successfully',
                'data' => ['plan' => $plan]
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updatePlan(Request $request, int $id)
    {
        try {
            $plan = $this->planService->updatePlan($id, $request->all());

            if (!$plan) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Plan not found'
                ], 404);
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Plan updated successfully',
                'data' => ['plan' => $plan]
            ]);


        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
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
}