<?php

namespace App\Controllers;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Mail\MailManager;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Mail\NewsletterSignupConfirmationWithTracking;
use App\Models\Member;
use App\Models\MemberRole;
use App\Repositories\NewsletterRepository;
use App\Repositories\SubscriberRepository;
use App\Services\EmailVerificationService;
use App\Services\NewsletterSignupService;

class NewsletterController extends Controller
{
    private NewsletterSignupService $service;

    public function __construct(
        private readonly SubscriberRepository     $repository,
        private readonly EmailVerificationService $emailVerificationService,
        private readonly NewsletterRepository     $newsletterRepository
    )
    {
        parent::__construct();
    }

    public function index()
    {
        try {
            $siteId = SiteContext::getId();
            $availableNewsletters = $this->newsletterRepository->where('site_id', $siteId)
                ->where('active', true)
                ->get();
            return $this->resourceResponse(['newsletters' => $availableNewsletters->toArray()]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function create(Request $request): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();

            $data = [
                'site_id' => $siteId,
                'title' => $request->input('title'),
                'content' => $request->input('content'),
                'interval' => $request->input('interval'),
                'active' => $request->input('active', true),
                'content_type' => $request->input('content_type', 'manual'),
                'max_pages' => $request->input('max_pages', 10),
                'sort_by' => $request->input('sort_by', 'published_at'),
                'sort_order' => $request->input('sort_order', 'desc'),
                'template' => $request->input('template', 'default')
            ];

            $newsletter = $this->newsletterRepository->create($data);

            return $this->jsonResponse([
                'newsletter' => $newsletter->toArray()
            ], 201);

        } catch (\Exception $e) {
            Logger::error('Failed to create newsletter', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('Failed to create newsletter: ' . $e->getMessage(), 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();
            $newsletter = $this->newsletterRepository->find($id);

            if (!$newsletter || $newsletter->site_id !== $siteId) {
                return $this->errorResponse('Newsletter not found', 404);
            }

            return $this->resourceResponse(['newsletter' => $newsletter->toArray()]);

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();
            $newsletter = $this->newsletterRepository->find($id);

            if (!$newsletter || $newsletter->site_id !== $siteId) {
                return $this->errorResponse('Newsletter not found', 404);
            }

            $data = array_filter([
                'title' => $request->input('title'),
                'content' => $request->input('content'),
                'interval' => $request->input('interval'),
                'active' => $request->input('active'),
                'content_type' => $request->input('content_type'),
                'max_pages' => $request->input('max_pages'),
                'sort_by' => $request->input('sort_by'),
                'sort_order' => $request->input('sort_order'),
                'template' => $request->input('template')
            ], fn($value) => $value !== null);

            $updated = $this->newsletterRepository->update($id, $data);

            return $this->successResponse('Newsletter updated successfully', [
                'newsletter' => $updated->toArray()
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to update newsletter', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to update newsletter: ' . $e->getMessage(), 500);
        }
    }

    public function delete(Request $request, int $id): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();
            $newsletter = $this->newsletterRepository->find($id);

            if (!$newsletter || $newsletter->site_id !== $siteId) {
                return $this->errorResponse('Newsletter not found', 404);
            }

            $this->newsletterRepository->delete($id);

            return $this->successResponse('Newsletter deleted successfully');

        } catch (\Exception $e) {
            Logger::error('Failed to delete newsletter', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to delete newsletter: ' . $e->getMessage(), 500);
        }
    }

    public function send(Request $request, int $id): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();
            $newsletter = $this->newsletterRepository->find($id);

            if (!$newsletter || $newsletter->site_id !== $siteId) {
                return $this->errorResponse('Newsletter not found', 404);
            }

            // Get all confirmed subscribers
            $subscribers = $this->repository->where('site_id', $siteId)
                ->where('confirmed', true)
                ->get();

            if ($subscribers->isEmpty()) {
                return $this->errorResponse('No confirmed subscribers to send to', 400);
            }

            // TODO: Implement actual newsletter sending logic
            // This would typically involve queuing jobs to send emails

            // Update last_sent timestamp
            $this->newsletterRepository->update($id, [
                'last_sent' => date('Y-m-d H:i:s')
            ]);

            return $this->successResponse('Newsletter sent successfully', [
                'sent_to' => $subscribers->count(),
                'newsletter_id' => $id
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to send newsletter', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to send newsletter: ' . $e->getMessage(), 500);
        }
    }

    public function getNewsletterSubscribers(Request $request, int $id): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();
            $newsletter = $this->newsletterRepository->find($id);

            if (!$newsletter || $newsletter->site_id !== $siteId) {
                return $this->errorResponse('Newsletter not found', 404);
            }

            $subscribers = $this->repository->where('site_id', $siteId)->get();

            return $this->resourceResponse(['subscribers' => $subscribers->toArray()]);

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
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

            // Send confirmation email
            $confirmationToken = $result['confirmation_token'];
            $this->sendSignupConfirmationEmail($email, $confirmationToken, $firstName);

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

    private function sendSignupConfirmationEmail(string $email, string $token, ?string $firstName = null): void
    {
        $mailable = new NewsletterSignupConfirmationWithTracking(
            $email,
            $token,
            $firstName
        );

        try {
            MailManager::getInstance()->send($mailable);
        } catch (\Exception $e) {
            Logger::error('Failed to send newsletter confirmation email', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
        }
    }
}