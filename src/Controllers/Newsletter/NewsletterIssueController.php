<?php

namespace App\Controllers\Newsletter;

use App\Controllers\Controller;
use App\DTO\Newsletters\IssueManualSendDTO;
use App\DTO\Newsletters\NewsletterIssueDTO;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Repositories\Newsletters\NewsletterIssueRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Requests\CreateNewsletterIssueRequest;
use App\Services\Newsletter\NewsletterIssueService;

/**
 * Handles newsletter issue CRUD and dispatch endpoints.
 *
 * Routes (all scoped to the current site via middleware):
 *
 *   GET    /api/newsletters/{newsletterId}/issues
 *   POST   /api/newsletters/{newsletterId}/issues
 *   GET    /api/newsletters/{newsletterId}/issues/{issueId}
 *   POST   /api/newsletters/{newsletterId}/issues/{issueId}/revert
 *   POST   /api/newsletters/{newsletterId}/issues/{issueId}/send   (pipeline send)
 *   POST   /api/newsletter-issues/{issueId}/send                    (manual ad-hoc send)
 */
class NewsletterIssueController extends Controller
{
    public function __construct(
        private readonly NewsletterIssueService    $issueService,
        private readonly NewsletterRepository      $newsletterRepository,
        private readonly NewsletterIssueRepository $issueRepository,
    )
    {
        parent::__construct();
    }

    // =========================================================================
    // List
    // =========================================================================

    /**
     * GET /api/newsletters/{newsletterId}/issues
     *
     * Returns all issues for a newsletter, newest-first.
     */
    public function index(Request $request, int $newsletterId): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();

            if (!$this->newsletterBelongsToSite($newsletterId, $siteId)) {
                return $this->errorResponse('Newsletter not found', 404);
            }

            $issues = $this->issueService->listIssues($newsletterId, $siteId);

            return $this->resourceResponse(['issues' => $issues]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // =========================================================================
    // Create
    // =========================================================================

    private function newsletterBelongsToSite(int $newsletterId, int $siteId): bool
    {
        $newsletter = $this->newsletterRepository->find($newsletterId);

        return $newsletter !== null && $newsletter->site_id === $siteId;
    }

    // =========================================================================
    // Show
    // =========================================================================

    public function store(CreateNewsletterIssueRequest $request, int $newsletterId): JsonResponse
    {
        try {
            $siteId = SiteContext::getId();

            if (!$this->newsletterBelongsToSite($newsletterId, $siteId)) {
                return $this->errorResponse('Newsletter not found', 404);
            }

            $dto = NewsletterIssueDTO::fromArray($request->validated());
            $issue = $this->issueService->createIssue($newsletterId, $siteId, $dto);

            return $this->resourceResponse(['issue' => $issue->toArray()], 201);
        } catch (ValidationException $validationException) {
            return $this->errorResponse('Validation failed', 422, $validationException->getErrors());
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        } catch (\Exception $e) {
            Logger::error('Failed to create newsletter issue', [
                'newsletter_id' => $newsletterId,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to create issue: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    // Revert
    // =========================================================================

    /**
     * GET /api/newsletters/{newsletterId}/issues/{issueId}
     */
    public function show(Request $request, int $newsletterId, int $issueId): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();
            $issue = $this->issueRepository->find($issueId);

            if (!$issue || $issue->site_id !== $siteId || $issue->newsletter_id !== $newsletterId) {
                return $this->errorResponse('Issue not found', 404);
            }

            return $this->resourceResponse(['issue' => $issue->toArray()]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // =========================================================================
    // Pipeline send (existing — routes to standard send pipeline)
    // =========================================================================

    /**
     * POST /api/newsletters/{newsletterId}/issues/{issueId}/revert
     *
     * Read-only operation — returns the issue snapshot so the frontend can
     * reload the editor.  Does NOT mutate the newsletter record.
     */
    public function revert(Request $request, int $newsletterId, int $issueId): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();

            $result = $this->issueService->getIssueSnapshot($newsletterId, $issueId, $siteId);

            return $this->resourceResponse([
                'issue' => $result['issue']->toArray(),
                'snapshot_json' => $result['snapshot_json'],
            ]);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        } catch (\Exception $e) {
            Logger::error('Failed to revert newsletter issue', [
                'newsletter_id' => $newsletterId,
                'issue_id' => $issueId,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to load snapshot: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    // Manual ad-hoc send
    // =========================================================================

    /**
     * POST /api/newsletters/{newsletterId}/issues/{issueId}/send
     *
     * Sends via the standard pipeline and transitions issue status to "sent".
     */
    public function send(Request $request, int $newsletterId, int $issueId): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();

            if (!$this->newsletterBelongsToSite($newsletterId, $siteId)) {
                return $this->errorResponse('Newsletter not found', 404);
            }

            $result = $this->issueService->sendIssue(
                $issueId,
                $siteId,
                MemberAuth::getMember()
            );

            if (!$result['success'] && empty($result['partial_failure'])) {
                return $this->errorResponse($result['error'] ?? 'Send failed', 400);
            }

            return $this->successResponse('Issue sent successfully', [
                'issue_id' => $issueId,
                'newsletter_id' => $newsletterId,
                'send_id' => $result['send_id'] ?? null,
                'sent_to' => $result['recipients'] ?? 0,
                'failed' => $result['failed'] ?? 0,
                'partial' => !empty($result['partial_failure']),
            ]);
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        } catch (\Exception $e) {
            Logger::error('Failed to send newsletter issue', [
                'newsletter_id' => $newsletterId,
                'issue_id' => $issueId,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to send issue: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * POST /api/newsletter-issues/{issueId}/send
     *
     * Dispatches an issue to all subscribers or a custom email list without
     * transitioning the issue status.  Queued asynchronously.
     *
     * Body:
     *   send_type     "all" | "custom"
     *   custom_emails string[]   — required when send_type = "custom"
     */
    public function manualSend(Request $request, int $issueId): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();

            $dto = IssueManualSendDTO::fromArray($request->all());
            $result = $this->issueService->manualSendIssue(
                $issueId,
                $siteId,
                $dto,
                MemberAuth::getMember()
            );

            return $this->successResponse($result['message'] ?? 'Issue queued', [
                'queued' => true,
                'send_type' => $dto->sendType,
                'recipients' => $dto->isCustom() ? count($dto->customEmails) : null,
                'success' => true,
                'message' => $result['message'] ?? 'Issue queued for delivery',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        } catch (\Exception $e) {
            Logger::error('Failed to queue manual newsletter issue send', [
                'issue_id' => $issueId,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to queue send: ' . $e->getMessage(), 500);
        }
    }
}