<?php

namespace App\Controllers;

use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Repositories\SubscriberRepository;
use App\Services\NewsletterSignupService;

class NewsletterController extends Controller
{
    private NewsletterSignupService $service;
    private SubscriberRepository $repository;

    public function __construct()
    {
        parent::__construct();
        $this->repository = new SubscriberRepository();
    }

    public function signup(Request $request): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();
            $this->service = new NewsletterSignupService($this->repository, $siteId);

            $email = $request->input('email');
            $result = $this->service->signup($email);

            if ($result['success']) {
                return $this->successResponse('Signup successful', $result);
            }

            return $this->errorResponse($result['error'], 400);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function confirm(Request $request): JsonResponse
    {
        $siteId = $request->getSiteId();
        $this->service = new NewsletterSignupService($this->repository, $siteId);

        $token = $request->input('token');
        $result = $this->service->confirm($token);

        if ($result['success']) {
            return $this->successResponse('Subscription confirmed', $result);
        }

        return $this->errorResponse($result['error'], 400);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $siteId = $request->getSiteId();
        $this->service = new NewsletterSignupService($this->repository, $siteId);

        $token = $request->input('token');
        $result = $this->service->unsubscribe($token);

        if ($result['success']) {
            return $this->successResponse('Unsubscribed successfully', $result);
        }

        return $this->errorResponse($result['error'], 400);
    }

    public function getSubscribers(Request $request): JsonResponse
    {
        $siteId = $request->getSiteId();
        $this->service = new NewsletterSignupService($this->repository, $siteId);

        $subscribers = $this->service->getConfirmedSubscribers();

        return $this->jsonResponse([
            'subscribers' => $subscribers,
            'count' => count($subscribers)
        ]);
    }
}