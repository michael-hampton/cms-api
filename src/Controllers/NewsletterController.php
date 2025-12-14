<?php

namespace App\Controllers;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Models\Member;
use App\Models\MemberRole;
use App\Repositories\SubscriberRepository;
use App\Services\EmailVerificationService;
use App\Services\NewsletterSignupService;

class NewsletterController extends Controller
{
    private NewsletterSignupService $service;

    public function __construct(
        private readonly SubscriberRepository     $repository,
        private readonly EmailVerificationService $emailVerificationService)
    {
        parent::__construct();
    }

    public function signup(Request $request): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();
            $this->service = new NewsletterSignupService($this->repository, $siteId);

            $email = $request->input('email');
            $createAccount = $request->input('create_account', false);
            $firstName = $request->input('first_name');
            $lastName = $request->input('last_name');
            $password = $request->input('password');

            // Newsletter signup
            $result = $this->service->signup($email);

            if (!$result['success']) {
                return $this->errorResponse($result['error'], 400);
            }

            // Handle account creation if requested
            if ($createAccount && $firstName && $lastName && $password) {
                // Check if member already exists
                $existingMember = Member::findByEmail($email, $siteId);

                if ($existingMember) {
                    return $this->successResponse('Newsletter subscription successful', [
                        'subscribed' => true,
                        'account_created' => false,
                        'message' => 'You already have an account. Please log in.',
                        'account_exists' => true
                    ]);
                }

                // Create new member account
                $member = Member::create([
                    'site_id' => $siteId,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'is_active' => true
                ]);

                // Assign default role
                $defaultRole = MemberRole::findBySlug('basic', $siteId);
                if ($defaultRole) {
                    $member->roles(true)->attach($defaultRole->id);
                }

                // Send verification email
                $token = $this->emailVerificationService->generateVerificationToken($member);
                $this->emailVerificationService->sendVerificationEmail($member, $token);

                // Auto-login the user
                try {
                    MemberAuth::login($member);

                    return $this->successResponse('Newsletter subscription and account created successfully', [
                        'subscribed' => true,
                        'account_created' => true,
                        'logged_in' => true,
                        'requires_verification' => true,
                        'member' => [
                            'id' => $member->id,
                            'email' => $member->email,
                            'first_name' => $member->first_name,
                            'last_name' => $member->last_name,
                            'is_verified' => $member->isEmailVerified()
                        ]
                    ]);
                } catch (\Exception $e) {
                    // Account created but login failed
                    return $this->successResponse('Account created successfully', [
                        'subscribed' => true,
                        'account_created' => true,
                        'logged_in' => false,
                        'message' => 'Account created. Please log in manually.',
                        'requires_verification' => true
                    ]);
                }
            }

            // Newsletter only subscription
            return $this->successResponse('Newsletter subscription successful', [
                'subscribed' => true,
                'account_created' => false,
                'email' => $email,
                'confirmation_token' => $result['confirmation_token']
            ]);

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

        // Support both token-based and subscriber_id-based unsubscribe
        $token = $request->input('token');
        $subscriberId = $request->input('subscriber_id');

        if ($subscriberId) {
            // Unsubscribe by subscriber ID (for logged-in users)
            $result = $this->service->unsubscribeById($subscriberId, $siteId);
        } elseif ($token) {
            // Unsubscribe by token (for email links)
            $result = $this->service->unsubscribe($token);
        } else {
            return $this->errorResponse('Missing token or subscriber ID', 400);
        }

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