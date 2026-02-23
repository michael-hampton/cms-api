<?php

namespace App\Controllers\Newsletter;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Mail\MailManager;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Mail\Newsletters\NewsletterSignupConfirmationWithTracking;
use App\Models\Member;
use App\Models\MemberRole;
use App\Models\Newsletter;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Newsletters\NewsletterSendRecipientRepository;
use App\Repositories\Subscriptions\SubscriberRepository;
use App\Resources\NewsletterResource;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteriaParser;
use App\Search\SearchEngine;
use App\Services\Cms\CampaignService;
use App\Services\EmailVerificationService;
use App\Services\Newsletter\NewsletterSendService;
use App\Services\Newsletter\NewsletterSignupService;
use App\Services\Newsletter\NewsletterStatisticsService;
use Exception;

class NewsletterController extends Controller
{
    private NewsletterSignupService $service;

    public function __construct(
        private readonly SubscriberRepository     $repository,
        private readonly EmailVerificationService $emailVerificationService,
        private readonly NewsletterRepository    $newsletterRepository,
        private readonly NewsletterSignupService $newsletterSignupService,
        private readonly CampaignService         $campaignService,
        private readonly NewsletterSendService             $newsletterSendService,
        private readonly NewsletterSendRecipientRepository $newsletterSendRecipientRepository,
        private readonly NewsletterStatisticsService       $newsletterService
    )
    {
        parent::__construct();
    }

    public function index(Request $request, string $siteName): JsonResponse
    {
        try {
            $siteId = SiteContext::getId();

            $criteria = SearchCriteriaParser::fromRequest($request, $siteName);
            $configuration = SearchConfigurationFactory::create('newsletters');
            $engine = new SearchEngine($configuration);

            $queryBuilder = Newsletter::query();
            $result = $engine->search($queryBuilder, $criteria);

            $collection = new PaginatedResourceCollection($result, NewsletterResource::class);

            return $this->resourceResponse([
                'success' => true,
                'newsletters' => $collection->toArray(),
                'stats' => $this->newsletterService->getAllNewsletterStatistics($siteId)
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function statistics(): JsonResponse
    {
        try {
            $siteId = SiteContext::getId();
            $stats = $this->newsletterService->getAllNewsletterStatistics($siteId);

            return $this->resourceResponse([
                'success' => true,
                'statistics' => $stats
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function create(Request $request): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();
            $isDefault = $request->input('is_default', false);

            $data = [
                'site_id' => $siteId,
                'title' => $request->input('title'),
                'content' => $request->input('content'),
                'interval' => $request->input('interval'),
                'active' => $request->input('active', true),
                'is_default' => false, // Will be set below if needed
                'content_type' => $request->input('content_type', 'manual'),
                'max_pages' => $request->input('max_pages', 10),
                'sort_by' => $request->input('sort_by', 'published_at'),
                'sort_order' => $request->input('sort_order', 'desc'),
                'template' => $request->input('template', 'default')
            ];

            $newsletter = $this->newsletterRepository->create($data);

            // If this should be the default, or if it's the first newsletter for this site
            if ($isDefault || $this->newsletterRepository->where('site_id', $siteId)->count() === 1) {
                $newsletter->setAsDefault();
            }

            return $this->jsonResponse([
                'newsletter' => $newsletter->fresh()->toArray()
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

            // Handle is_default separately
            if ($request->has('is_default') && $request->input('is_default')) {
                $updated->setAsDefault();
                $updated = $updated->fresh();
            }

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

            $result = $this->newsletterSendService->sendNewsletter($newsletter, $siteId, MemberAuth::getMember());

            if (!$result['success']) {
                return $this->errorResponse($result['error'], 400);
            }

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

            $email = $request->input('email');
            $newsletterId = $request->input('newsletter_id'); // Can be null
            $campaignSlug = $request->input('campaign'); // NEW
            $createAccount = $request->input('create_account', false);
            $firstName = $request->input('first_name');
            $lastName = $request->input('last_name');
            $password = $request->input('password');

            // Resolve campaign and newsletter using CampaignService
            $resolution = $this->campaignService->resolveCampaignOrNewsletter(
                $campaignSlug,
                $newsletterId,
                $siteId
            );

            if (!$resolution->success) {
                return $this->errorResponse($resolution->error, 400);
            }

            $resolvedNewsletterId = $resolution->newsletterId;
            $campaignId = $resolution->campaignId;
            $campaign = $resolution->campaign;

            // Newsletter signup with optional newsletter_id
            $result = $this->newsletterSignupService->signup(
                $email,
                true,
                $resolvedNewsletterId,
                $siteId,
                $campaignId  // NEW
            );

            if (!$result['success']) {
                return $this->errorResponse($result['error'], 400);
            }

            // Track campaign signup if campaign exists
            if ($campaignId) {
                $this->campaignService->trackCampaignSignup($campaignId);
            }

            // Send confirmation email
            if (!isset($result['resubscribed']) || !$result['resubscribed']) {
                $this->sendSignupConfirmationEmail(
                    $email,
                    $result['confirmation_token'],
                    $firstName
                );
            }

            // Handle account creation if requested
            if ($createAccount && $firstName && $lastName && $password) {
                // Check if member already exists
                $existingMember = Member::findByEmail($email, $siteId);

                if ($existingMember) {
                    return $this->successResponse('Newsletter subscription successful', [
                        'subscribed' => true,
                        'newsletter_id' => $result['newsletter_id'],
                        'account_created' => false,
                        'message' => 'You already have an account. Please log in.',
                        'account_exists' => true,
                        'resubscribed' => $result['resubscribed'] ?? false
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

                    // Get available newsletters
                    $availableNewsletters = $this->formatNewslettersForResponse(
                        $this->newsletterRepository->getActive($siteId)
                    );

                    return $this->successResponse('Newsletter subscription and account created successfully', [
                        'subscribed' => true,
                        'resubscribed' => $result['resubscribed'] ?? false,
                        'newsletter_id' => $result['newsletter_id'],
                        'account_created' => true,
                        'logged_in' => true,
                        'requires_verification' => true,
                        'member' => [
                            'id' => $member->id,
                            'email' => $member->email,
                            'first_name' => $member->first_name,
                            'last_name' => $member->last_name,
                            'is_verified' => $member->isEmailVerified()
                        ],
                        'available_newsletters' => $availableNewsletters
                    ]);
                } catch (\Exception $e) {
                    $availableNewsletters = $this->formatNewslettersForResponse(
                        $this->newsletterRepository->getActive($siteId)
                    );

                    // Account created but login failed
                    return $this->successResponse('Account created successfully', [
                        'subscribed' => true,
                        'newsletter_id' => $result['newsletter_id'],
                        'account_created' => true,
                        'logged_in' => false,
                        'message' => 'Account created. Please log in manually.',
                        'requires_verification' => true,
                        'available_newsletters' => $availableNewsletters
                    ]);
                }
            }

            // Get available newsletters
            $availableNewsletters = $this->formatNewslettersForResponse(
                $this->newsletterRepository->getActive($siteId)
            );

            $responseData = [
                'subscribed' => true,
                'newsletter_id' => $result['newsletter_id'],
                'campaign_id' => $campaignId,
                'account_created' => false,
                'email' => $email,
                'confirmation_token' => $result['confirmation_token'],
                'available_newsletters' => $availableNewsletters,
                'resubscribed' => $result['resubscribed'] ?? false
            ];

            if ($campaign) {
                $responseData['campaign'] = [
                    'name' => $campaign->name,
                    'slug' => $campaign->slug,
                    'gates_premium_content' => $campaign->gates_premium_content,
                ];
            }

            return $this->successResponse('Newsletter subscription successful', $responseData);

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function confirm(Request $request): JsonResponse
    {
        $siteId = $request->getSiteId();

        $token = $request->input('token');
        $result = $this->newsletterSignupService->confirm($token, $siteId);

        if ($result['success']) {
            return $this->successResponse('Subscription confirmed', $result);
        }

        return $this->errorResponse($result['error'], 400);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $siteId = $request->getSiteId();

        // Support both token-based and subscriber_id-based unsubscribe
        $token = $request->input('token');
        $subscriberId = $request->input('subscriber_id');

        if ($subscriberId) {
            // Unsubscribe by subscriber ID (for logged-in users)
            $result = $this->newsletterSignupService->unsubscribeById($subscriberId, $siteId);
        } elseif ($token) {
            // Unsubscribe by token (for email links)
            $result = $this->newsletterSignupService->unsubscribe($token, $siteId);
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

        $subscribers = $this->newsletterSignupService->getConfirmedSubscribers($siteId);

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

    /**
     * Format newsletters collection for API response
     */
    private function formatNewslettersForResponse($newsletters): array
    {
        $formatted = [];

        $member = MemberAuth::check() ? MemberAuth::getMember() : null;
        $siteId = SiteContext::getId();

        $subscribedIds = [];
        if ($member !== null) {
            // Resolve which newsletter IDs this member is subscribed to.
            // Adjust the relation/method name to match your actual Member model.
            $subscribedIds = $this->newsletterRepository
                ->getNewslettersForMember($member)
                ->pluck('id')
                ->toArray();
        }

        foreach ($newsletters as $newsletter) {
            $formatted[] = [
                'id' => $newsletter->id,
                'title' => $newsletter->title,
                'description' => $newsletter->content ?? '',
                'interval' => $newsletter->interval,
                'frequency' => $this->getFrequencyLabel($newsletter->interval),
                'is_subscribed' => in_array($newsletter->id, $subscribedIds, true),
            ];
        }

        return $formatted;
    }

    /**
     * Convert interval to human-readable frequency label
     */
    private function getFrequencyLabel(string $interval): string
    {
        $labels = [
            'daily' => 'FIVE TIMES A WEEK',
            'weekly' => 'ONCE A WEEK',
            'biweekly' => 'TWICE A MONTH',
            'monthly' => 'ONCE A MONTH',
        ];

        return $labels[$interval] ?? strtoupper($interval);
    }

    public function preview(Request $request, int $id): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();
            $newsletter = $this->newsletterRepository->find($id);

            if (!$newsletter || $newsletter->site_id !== $siteId) {
                return $this->errorResponse('Newsletter not found', 404);
            }

            $previewEmails = $request->input('preview_emails', []);

            if (empty($previewEmails)) {
                return $this->errorResponse('No preview email addresses provided', 400);
            }

            $result = $this->newsletterSendService->previewNewsletter(
                $newsletter,
                $previewEmails,
                $siteId
            );

            if (!$result['success']) {
                return $this->errorResponse($result['error'], 400);
            }

            return $this->successResponse('Preview sent successfully', $result);

        } catch (\Exception $e) {
            Logger::error('Failed to send newsletter preview', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to send preview: ' . $e->getMessage(), 500);
        }
    }

    public function retrySend(Request $request, int $id, int $sendId): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();
            $newsletter = $this->newsletterRepository->find($id);

            if (!$newsletter || $newsletter->site_id !== $siteId) {
                return $this->errorResponse('Newsletter not found', 404);
            }

            $maxAttempts = $request->input('max_attempts', 3);

            $result = $this->newsletterSendService->retrySend($sendId, $maxAttempts);

            if (!$result['success']) {
                return $this->errorResponse($result['error'], 400);
            }

            return $this->successResponse('Retry completed', $result);

        } catch (\Exception $e) {
            Logger::error('Failed to retry newsletter send', [
                'id' => $id,
                'send_id' => $sendId,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to retry send: ' . $e->getMessage(), 500);
        }
    }

    public function getSendStatistics(Request $request, int $id, int $sendId): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();
            $newsletter = $this->newsletterRepository->find($id);

            if (!$newsletter || $newsletter->site_id !== $siteId) {
                return $this->errorResponse('Newsletter not found', 404);
            }

            $stats = $this->newsletterSendRecipientRepository->getStatistics($sendId);

            return $this->resourceResponse(['statistics' => $stats]);

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}