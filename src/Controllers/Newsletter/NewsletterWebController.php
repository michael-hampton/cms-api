<?php

namespace App\Controllers\Newsletter;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Http\StreamedResponse;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Models\Member;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Newsletters\NewsletterSendPageViewRepository;
use App\Repositories\Newsletters\NewsletterSendRepository;
use App\Repositories\Subscriptions\SubscriberRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Newsletter\NewsletterArchiveService;
use App\Services\Newsletter\NewsletterPageBuilderService;
use Dompdf\Dompdf;
use Dompdf\Options;

class NewsletterWebController extends Controller
{
    public function __construct(
        private readonly NewsletterRepository         $newsletterRepository,
        private readonly NewsletterPageBuilderService $pageBuilderService,
        private readonly NewsletterArchiveService         $archiveService,
        private readonly SubscriptionRepository           $subscriptionRepository,
        private readonly SubscriberRepository             $subscriberRepository,
        private readonly NewsletterSendRepository         $sendRepository,
        private readonly NewsletterSendPageViewRepository $sendPageViewRepository,
        private readonly PageRepository                   $pageRepository,
    )
    {
        parent::__construct();
    }

    public function index()
    {
        $siteId = SiteContext::getId();
        $newsletters = $this->newsletterRepository->getPublished($siteId);

        return $this->view('newsletters/index', [
            'site' => SiteContext::get(),
            'newsletters' => $newsletters
        ]);
    }

    public function show(int $id, Request $request)
    {
        $token = $request->query('token');
        $sendId = $request->query('send_id');

        $newsletter = $this->newsletterRepository->find($id);

        if (!$newsletter) {
            return $this->redirectResponse('', 404);
        }

        $newsletterSummary = $this->archiveService->getNewsletterArchive($newsletter->id, MemberAuth::id() ?? null);

        if (!empty($newsletterSummary['requires_auth'])) {
            return $this->view('newsletters/archive-access-required', [
                'site' => SiteContext::get(),
                'newsletter' => $newsletter,
                'message' => $newsletterSummary['message'],
                'access_type' => $newsletterSummary['access_type'],
                'token' => $token,
                'is_logged_in' => MemberAuth::check(),
                'single_access_available' => $this->isSingleAccessAvailable($newsletter) ?? true
            ]);
        }

        if ($newsletter->site_id !== SiteContext::getId()) {
            return $this->redirectResponse('', 403);
        }

        // If viewing a specific edition, use cached content
        if ($sendId) {
            $send = $this->sendRepository->findByNewsletterAndSendId($newsletter->id, $sendId);

            if ($send && $send->html_snapshot) {
                // Use cached HTML - no tracking for archived views
                $html = $send->html_snapshot;

                // Remove tracking placeholders since this is an archive view
                $html = str_replace('{{SEND_ID}}', '', $html);
                $html = str_replace('{{TRACKING_EMAIL}}', '', $html);
                // Convert tracking URLs back to direct URLs
                $html = preg_replace(
                    '/\/newsletters\/track-view\?send_id=&page_id=(\d+)&e=&redirect=([^"\']+)/',
                    '$2',
                    $html
                );

                $pages = $send->content_snapshot ?? [];

                return $this->view('newsletters/show', [
                    'site' => SiteContext::get(),
                    'newsletter' => $newsletter,
                    'html' => $html,
                    'pages' => collect($pages),
                    'token' => $token,
                    'newsletter_summary' => $newsletterSummary,
                    'latestNewsletter' => $newsletterSummary['latest_edition'],
                    'newslettersByYear' => $newsletterSummary['grouped_editions'],
                    'hasApp' => $this->archiveService->hasApp($newsletter->id),
                    'send_id' => $sendId,
                    'send_date' => $send->sent_at,
                    'is_archive_view' => true,
                    'years' => $newsletterSummary['years_available']
                ]);
            }
        }

        // Default behavior: generate current newsletter (no sendId for tracking)
        $pages = collect([]);
        if ($newsletter->isAutomated()) {
            $pages = $this->pageBuilderService->getPagesForNewsletter(
                $newsletter,
                $newsletter->site_id
            );
        }

        // Build HTML content without tracking (live view, not email)
        $html = $this->pageBuilderService->buildNewsletterHtml(
            $newsletter,
            $pages,
            $token,
            true,
            null // No sendId = no tracking
        );

        return $this->view('newsletters/show', [
            'site' => SiteContext::get(),
            'newsletter' => $newsletter,
            'html' => $html,
            'pages' => $pages,
            'token' => $token,
            'newsletter_summary' => $newsletterSummary,
            'latestNewsletter' => $newsletterSummary['latest_edition'],
            'newslettersByYear' => $newsletterSummary['grouped_editions'],
            'hasApp' => $this->archiveService->hasApp($newsletter->id),
            'is_archive_view' => false,
            'years' => $newsletterSummary['years_available']
        ]);
    }

    public function viewNewsletter(int $id, Request $request)
    {
        $token = $request->query('token');
        $sendId = $request->query('send_id');

        $newsletter = $this->newsletterRepository->find($id);

        if (!$newsletter) {
            return $this->redirectResponse('', 404);
        }

        if ($newsletter->site_id !== SiteContext::getId()) {
            return $this->redirectResponse('', 403);
        }

        // Try to get cached content first
        if ($sendId) {
            $send = $this->sendRepository->findByNewsletterAndSendId($newsletter->id, $sendId);

            if ($send && $send->html_snapshot) {
                $html = $send->html_snapshot;

                // Remove tracking placeholders for archive view
                $html = str_replace('{{SEND_ID}}', '', $html);
                $html = str_replace('{{TRACKING_EMAIL}}', '', $html);
                $html = preg_replace(
                    '/\/newsletters\/track-view\?send_id=&page_id=(\d+)&e=&redirect=([^"\']+)/',
                    '$2',
                    $html
                );

                return $this->view('newsletters/view', [
                    'site' => SiteContext::get(),
                    'newsletter' => $newsletter,
                    'html' => $html,
                    'pages' => collect($send->content_snapshot ?? []),
                    'token' => $token,
                    'send_id' => $sendId,
                    'send_date' => $send->sent_at,
                    'is_archive_view' => true
                ]);
            }
        }

        // Fallback: Generate pages for newsletter
        $pages = collect([]);
        $asOfDate = null;

        if ($sendId) {
            $send = $this->sendRepository->findByNewsletterAndSendId($newsletter->id, $sendId);
            if ($send) {
                $asOfDate = $send->sent_at;
            }
        }

        if ($newsletter->isAutomated()) {
            $pages = $this->pageBuilderService->getPagesForNewsletter(
                $newsletter,
                $newsletter->site_id,
                $asOfDate
            );
        }

        // Build HTML content without tracking
        $html = $this->pageBuilderService->buildNewsletterHtml(
            $newsletter,
            $pages,
            $token,
            true,
            null // No tracking for archive/preview views
        );

        $html = urldecode($html);

        $html = str_replace('{{SEND_ID}}', '', $html);
        $html = str_replace('{{TRACKING_EMAIL}}', '', $html);
        $html = preg_replace(
            '/\/newsletters\/track-view\?send_id=&page_id=(\d+)&e=&redirect=([^"\']+)/',
            '$2',
            $html
        );

        return $this->view('newsletters/view', [
            'site' => SiteContext::get(),
            'newsletter' => $newsletter,
            'html' => $html,
            'pages' => $pages,
            'token' => $token,
            'send_id' => $sendId,
            'send_date' => $asOfDate,
            'is_archive_view' => false
        ]);
    }

    public function archive()
    {
        $siteId = SiteContext::getId();
        $newsletters = $this->newsletterRepository->getArchive($siteId);

        return $this->view('newsletters/archive', [
            'site' => SiteContext::get(),
            'newsletters' => $newsletters
        ]);
    }

    public function downloadPdf(int $id, Request $request)
    {
        $token = $request->query('token');
        $newsletter = $this->newsletterRepository->find($id);

        if (!$newsletter) {
            return $this->redirectResponse('', 404);
        }

        if ($newsletter->site_id !== SiteContext::getId()) {
            return $this->redirectResponse('', 403);
        }

        // Get pages for automated newsletter
        $pages = collect([]);
        if ($newsletter->isAutomated()) {
            $pages = $this->pageBuilderService->getPagesForNewsletter(
                $newsletter,
                $newsletter->site_id
            );
        }

        // Build HTML content
        $html = $this->pageBuilderService->buildNewsletterHtml(
            $newsletter,
            $pages,
            $token,
            true
        );

        // Wrap in complete HTML document for PDF
        $pdfHtml = $this->buildPdfHtml($newsletter, $html);

        // Generate PDF
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($pdfHtml);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Generate filename
        $filename = $this->sanitizeFilename($newsletter->title) . '-' . date('Y-m-d') . '.pdf';

        // Stream PDF to browser
        return new StreamedResponse(function () use ($dompdf) {
            echo $dompdf->output();
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function buildPdfHtml($newsletter, $content): string
    {
        $site = SiteContext::get();

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{$newsletter->title}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #333;
        }
        
        .header {
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 24pt;
            color: #1a202c;
            margin-bottom: 10px;
        }
        
        .header .meta {
            font-size: 10pt;
            color: #666;
        }
        
        .content {
            margin-bottom: 40px;
        }
        
        .content img {
            max-width: 100%;
            height: auto;
        }
        
        .content h1, .content h2, .content h3 {
            margin-top: 20px;
            margin-bottom: 10px;
            color: #1a202c;
        }
        
        .content p {
            margin-bottom: 12px;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            font-size: 9pt;
            color: #666;
            text-align: center;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        table th,
        table td {
            padding: 8px;
            border: 1px solid #e2e8f0;
            text-align: left;
        }
        
        table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        blockquote {
            border-left: 4px solid #667eea;
            padding-left: 20px;
            margin: 20px 0;
            font-style: italic;
        }
        
        ul, ol {
            margin: 10px 0 10px 30px;
        }
        
        li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{$newsletter->title}</h1>
        <div class="meta">
            {$site->name} | {$newsletter->created_at->format('F d, Y')}
        </div>
    </div>
    
    <div class="content">
        {$content}
    </div>
    
    <div class="footer">
        <p>&copy; {$site->name} | Generated on " . date('F d, Y') . "</p>
    </div>
</body>
</html>
HTML;
    }

    private function sanitizeFilename(string $filename): string
    {
        // Remove special characters and spaces
        $filename = preg_replace('/[^a-zA-Z0-9-_]/', '-', $filename);
        // Remove multiple dashes
        $filename = preg_replace('/-+/', '-', $filename);
        // Trim dashes from ends
        $filename = trim($filename, '-');
        // Limit length
        return substr($filename, 0, 50);
    }

    /**
     * Search newsletters (AJAX endpoint for index page)
     */
    public function search(Request $request)
    {
        $siteId = SiteContext::getId();

        // Get filter parameters
        $filters = [
            'search' => $request->get('search', ''),
            'interval' => $request->get('interval', ''),
            'year' => $request->get('year', ''),
            'date_from' => $request->get('date_from', ''),
            'date_to' => $request->get('date_to', ''),
            'sort_by' => $request->get('sort_by', 'last_sent'),
            'sort_order' => $request->get('sort_order', 'desc'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, fn($value) => $value !== '');

        $page = (int)$request->get('page', 1);
        $perPage = 12;

        // Get filtered newsletters
        $result = $this->archiveService->searchNewsletters($siteId, $filters, $page, $perPage);

        // Format newsletters for JSON response
        $formattedNewsletters = $result['newsletters']->map(function ($newsletter) {
            return [
                'id' => $newsletter->id,
                'title' => $newsletter->title,
                'content' => $newsletter->content,
                'last_sent' => $newsletter->last_sent?->format('Y-m-d H:i:s'),
                'interval' => $newsletter->interval,
                'created_at' => $newsletter->created_at?->format('Y-m-d H:i:s'),
            ];
        })->toArray();

        return $this->resourceResponse([
            'success' => true,
            'newsletters' => $formattedNewsletters,
            'pagination' => $result['pagination'],
            'filters_applied' => $result['filters_applied'],
        ]);
    }

    /**
     * Search and filter newsletters (AJAX endpoint)
     */
    public function searchArchive(Request $request)
    {
        $siteId = SiteContext::getId();

        // Get filter parameters from request
        $filters = [
            'search' => $request->get('search', ''),
            'interval' => $request->get('interval', ''),
            'year' => $request->get('year', ''),
            'date_from' => $request->get('date_from', ''),
            'date_to' => $request->get('date_to', ''),
            'sort_by' => $request->get('sort_by', 'last_sent'),
            'sort_order' => $request->get('sort_order', 'desc'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, fn($value) => $value !== '');

        $page = (int)$request->get('page', 1);
        $perPage = 20;

        // Get filtered newsletters
        $result = $this->archiveService->searchNewsletters($siteId, $filters, $page, $perPage);

        // Format newsletters for JSON response
        $formattedNewsletters = $result['newsletters']->map(function ($newsletter) {
            return [
                'id' => $newsletter->id,
                'title' => $newsletter->title,
                'content' => $newsletter->content,
                'last_sent' => $newsletter->last_sent?->format('Y-m-d H:i:s'),
                'interval' => $newsletter->interval,
                'created_at' => $newsletter->created_at?->format('Y-m-d H:i:s'),
            ];
        })->toArray();

        return $this->resourceResponse([
            'success' => true,
            'newsletters' => $formattedNewsletters,
            'pagination' => $result['pagination'],
            'filters_applied' => $result['filters_applied'],
        ]);
    }

    /**
     * Toggle newsletter subscription (subscribe/unsubscribe)
     */
    public function toggle(Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->redirectResponse('', 401);
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        try {
            $data = $request->all();

            if (!isset($data['newsletter_id']) || !isset($data['subscribe'])) {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => 'Missing required parameters'
                ], 400);
            }

            $newsletterId = (int)$data['newsletter_id'];
            $subscribe = (bool)$data['subscribe'];

            // Get newsletter
            $newsletter = $this->newsletterRepository->find($newsletterId);

            if (!$newsletter || $newsletter->site_id !== $siteId) {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => 'Newsletter not found'
                ], 404);
            }

            // Check if newsletter is premium and member has access
            if ($newsletter->isPremium()) {
                $hasAccess = $this->checkPremiumAccess($member, $newsletter);

                if (!$hasAccess && $subscribe) {
                    return $this->resourceResponse([
                        'success' => false,
                        'message' => 'You need an active subscription to access this premium newsletter',
                        'requires_upgrade' => true
                    ], 403);
                }
            }

            // Find or create subscriber record
            $subscriber = $this->subscriberRepository->findExisting(
                $member->email,
                $newsletterId,
                $siteId
            );

            if ($subscribe) {
                if ($subscriber) {
                    // Resubscribe if previously unsubscribed
                    if ($subscriber->unsubscribed_at) {
                        $subscriber->resubscribe();
                        $message = 'Successfully resubscribed to newsletter';
                    } else {
                        $message = 'Already subscribed to this newsletter';
                    }
                } else {
                    // Create new subscription
                    $subscriber = $this->subscriberRepository->create([
                        'email' => $member->email,
                        'newsletter_id' => $newsletterId,
                        'site_id' => $siteId,
                        'confirmed' => true, // Auto-confirm for logged-in members
                        'confirmation_token' => bin2hex(random_bytes(32)),
                        'unsubscribe_token' => bin2hex(random_bytes(32)),
                        'subscribed_at' => now_datetime()->format('Y-m-d H:i:s')
                    ]);
                    $message = 'Successfully subscribed to newsletter';
                }

                Logger::info('Member subscribed to newsletter', [
                    'member_id' => $member->id,
                    'newsletter_id' => $newsletterId
                ]);
            } else {
                if ($subscriber && !$subscriber->unsubscribed_at) {
                    $subscriber->unsubscribe();
                    $message = 'Successfully unsubscribed from newsletter';

                    Logger::info('Member unsubscribed from newsletter', [
                        'member_id' => $member->id,
                        'newsletter_id' => $newsletterId
                    ]);
                } else {
                    $message = 'Already unsubscribed from this newsletter';
                }
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => $message,
                'subscribed' => $subscribe
            ]);

        } catch (\Exception $e) {
            Logger::error('Newsletter toggle failed', [
                'member_id' => $member->id,
                'error' => $e->getMessage()
            ]);

            return $this->resourceResponse([
                'success' => false,
                'message' => 'An error occurred. Please try again.'
            ], 500);
        }
    }

    /**
     * Check if member has premium access to newsletter
     */
    private function checkPremiumAccess(Member $member, $newsletter): bool
    {
        // Get member's active subscription
        $subscription = $this->subscriptionRepository->getActiveSubscriptionForMember(
            $member->id,
            SiteContext::getId()
        );

        if (!$subscription) {
            return false;
        }

        // Check if subscription grants access to this premium newsletter
        return $subscription->hasPremiumAccess('newsletter', $newsletter->slug);
    }

    private function isSingleAccessAvailable($newsletter): bool
    {
        // Check multiple conditions for single access availability:

        // 1. Check if newsletter explicitly allows single purchase
        if (isset($newsletter->allows_single_purchase)) {
            return (bool)$newsletter->allows_single_purchase;
        }

        // 2. Check if single content access feature is enabled for the site
        $site = SiteContext::get();
        if (isset($site->features['single_content_access'])) {
            return (bool)$site->features['single_content_access'];
        }

        // 3. Check if there's a price set for single purchase
        if (isset($newsletter->single_purchase_price) && $newsletter->single_purchase_price > 0) {
            return true;
        }

        // 4. Default: enabled for premium newsletters, disabled for free ones
        if (method_exists($newsletter, 'isPremium')) {
            return $newsletter->isPremium();
        }

        // Default: disabled
        return false;
    }

    /**
     * Track when a user clicks on a page link from a newsletter
     */
    /**
     * Track when a user clicks on a page link from a newsletter
     */
    public function trackPageView(Request $request)
    {
        try {
            $sendId = $request->input('send_id');
            $pageId = $request->input('page_id');
            $emailHash = $request->input('e'); // Hashed email
            $redirectUrl = $request->input('redirect');

            if (!$sendId || !$pageId) {
                // If missing params, just redirect to the page anyway
                if ($redirectUrl) {
                    return $this->redirectResponse($redirectUrl);
                }
                return $this->resourceResponse([
                    'success' => false,
                    'message' => 'Missing required parameters'
                ], 400);
            }

            // Get IP and user agent
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();

            // Track the view (we can't reverse the hash, so we just store the hash)
            $pageViewRepo = app(NewsletterSendPageViewRepository::class);
            $pageViewRepo->trackPageView($sendId, $pageId, $emailHash, $ipAddress, $userAgent);

            // Redirect to the actual page
            if ($redirectUrl) {
                return $this->redirectResponse($redirectUrl);
            }

            $page = app(PageRepository::class)->find($pageId);
            if ($page) {
                return $this->redirectResponse('/' . $page->slug);
            }

            return $this->resourceResponse([
                'success' => true
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to track newsletter page view', [
                'error' => $e->getMessage(),
                'send_id' => $request->input('send_id'),
                'page_id' => $request->input('page_id')
            ]);

            // Even if tracking fails, redirect the user
            $redirectUrl = $request->input('redirect');
            if ($redirectUrl) {
                return $this->redirectResponse($redirectUrl);
            }

            return $this->resourceResponse([
                'success' => false,
                'message' => 'Failed to track view'
            ], 500);
        }
    }

    /**
     * Get analytics for a newsletter send
     */
    public function sendAnalytics(int $newsletterId, int $sendId, Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->redirectResponse('', 401);
        }

        // Check if user has permission to view analytics (admin/editor check)
        // Implementation depends on your permission system

        $send = $this->sendRepository->findByNewsletterAndSendId($newsletterId, $sendId);

        if (!$send) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Newsletter send not found'
            ], 404);
        }

        $statistics = $this->sendPageViewRepository->getViewStatistics($sendId);

        // Calculate click-through rate
        if ($send->recipient_count > 0) {
            $statistics['click_through_rate'] =
                round(($statistics['unique_recipients'] / $send->recipient_count) * 100, 2);
        }

        return $this->resourceResponse([
            'success' => true,
            'send' => [
                'id' => $send->id,
                'sent_at' => $send->sent_at->format('Y-m-d H:i:s'),
                'recipient_count' => $send->recipient_count,
                'recipients' => $send->recipients
            ],
            'statistics' => $statistics
        ]);
    }

    /**
     * Show list of all editions for a newsletter
     */
    public function editions(int $id, Request $request)
    {
        $newsletter = $this->newsletterRepository->find($id);

        if (!$newsletter) {
            return $this->redirectResponse('', 404);
        }

        if ($newsletter->site_id !== SiteContext::getId()) {
            return $this->redirectResponse('', 403);
        }

        // Get all sends for this newsletter
        $sends = $this->sendRepository->getSendsForNewsletter($newsletter->id)?->toArray();

        // Group by year and month
        $groupedSends = [];
        foreach ($sends as $send) {
            $date = new \DateTime($send['sent_at']);
            $year = $date->format('Y');
            $month = $date->format('F');

            if (!isset($groupedSends[$year])) {
                $groupedSends[$year] = [];
            }
            if (!isset($groupedSends[$year][$month])) {
                $groupedSends[$year][$month] = [];
            }

            $groupedSends[$year][$month][] = [
                'id' => $send['id'],
                'sent_at' => $send['sent_at'],
                'recipient_count' => $send['recipient_count'],
                'page_count' => is_array($send['content_snapshot']) ? count($send['content_snapshot']) : 0,
                'view_url' => url("/newsletters/{$newsletter->id}?send_id={$send['id']}")
            ];
        }

        return $this->view('newsletters/editions', [
            'site' => SiteContext::get(),
            'newsletter' => $newsletter,
            'grouped_sends' => $groupedSends,
            'total_editions' => count($sends)
        ]);
    }
}