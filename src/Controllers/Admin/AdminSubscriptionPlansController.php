<?php

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\SubscriptionPlanRepository;
use App\Services\SubscriptionPlanService;

class AdminSubscriptionPlansController extends Controller
{
    public function __construct(
        private readonly SubscriptionPlanService $planService,
        private SubscriptionPlanRepository       $planRepository
    )
    {
        parent::__construct();
    }

    public function index()
    {
        $siteId = SiteContext::getId();
        $plansWithStats = $this->planService->getAllPlansWithStats($siteId);

        return $this->view('admin/subscription-plans/index', [
            'site' => SiteContext::get(),
            'plansWithStats' => $plansWithStats
        ]);
    }

    public function create()
    {
        return $this->view('admin/subscription-plans/create', [
            'site' => SiteContext::get()
        ]);
    }

    public function store(Request $request)
    {
        $siteId = SiteContext::getId();

        try {
            $plan = $this->planService->createPlan($request->all(), $siteId);

            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                return $this->jsonResponse([
                    'success' => true,
                    'message' => 'Plan created successfully',
                    'data' => ['plan' => $plan]
                ]);
            }

            $_SESSION['flash_success'] = 'Plan created successfully';
            return $this->redirect('/' . SiteContext::slug() . '/admin/subscription-plans');

        } catch (\Exception $e) {
            echo $e->getMessage();
            die;
            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }

            $_SESSION['flash_error'] = $e->getMessage();
            return $this->redirect('/admin/subscription-plans/create');
        }
    }

    public function edit(int $id)
    {
        $planWithStats = $this->planService->getPlanWithStats($id);

        if (empty($planWithStats)) {
            return $this->notFound('Plan not found');
        }

        return $this->view('admin/subscription-plans/edit', [
            'site' => SiteContext::get(),
            'plan' => $planWithStats['plan'],
            'subscriberCount' => $planWithStats['subscriber_count'],
            'revenue' => $planWithStats['revenue']
        ]);
    }

    public function update(Request $request, int $id)
    {
        try {
            $plan = $this->planService->updatePlan($id, $request->all());

            if (!$plan) {
                if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => 'Plan not found'
                    ], 404);
                }
                return $this->notFound('Plan not found');
            }

            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                return $this->jsonResponse([
                    'success' => true,
                    'message' => 'Plan updated successfully',
                    'data' => ['plan' => $plan]
                ]);
            }

            $_SESSION['flash_success'] = 'Plan updated successfully';
            return $this->redirect('/admin/subscription-plans');

        } catch (\Exception $e) {
            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }

            $_SESSION['flash_error'] = $e->getMessage();
            return $this->redirect("/admin/subscription-plans/{$id}/edit");
        }
    }

    public function destroy(Request $request, int $id)
    {
        try {
            $success = $this->planService->deletePlan($id);

            if (!$success) {
                if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => 'Failed to delete plan'
                    ], 500);
                }

                $_SESSION['flash_error'] = 'Failed to delete plan';
                return $this->redirect('/admin/subscription-plans');
            }

            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                return $this->jsonResponse([
                    'success' => true,
                    'message' => 'Plan deleted successfully'
                ]);
            }

            $_SESSION['flash_success'] = 'Plan deleted successfully';
            return $this->redirect('/admin/subscription-plans');

        } catch (\Exception $e) {
            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }

            $_SESSION['flash_error'] = $e->getMessage();
            return $this->redirect('/admin/subscription-plans');
        }
    }

    public function toggleActive(Request $request, int $id)
    {
        $success = $this->planService->togglePlanActive($id);

        if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
            return $this->jsonResponse([
                'success' => $success,
                'message' => $success ? 'Status updated' : 'Failed to update status'
            ]);
        }

        $_SESSION['flash_success'] = 'Plan status updated';
        return $this->redirect('/admin/subscription-plans');
    }

    public function toggleFeatured(Request $request, int $id)
    {
        $success = $this->planService->togglePlanFeatured($id);

        if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
            return $this->jsonResponse([
                'success' => $success,
                'message' => $success ? 'Featured status updated' : 'Failed to update'
            ]);
        }

        $_SESSION['flash_success'] = 'Featured status updated';
        return $this->redirect('/admin/subscription-plans');
    }
}