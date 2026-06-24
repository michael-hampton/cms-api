<?php

namespace App\Controllers\Crm;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Members\CommunicationLogRepository;
use App\Repositories\Members\CrmMemberRepository;

/**
 * CrmCommunicationsController
 *
 * Exposes a read-only communication history log for a member in the CRM.
 * Covers both transactional emails (receipts, confirmations, etc.) and
 * marketing emails (campaigns, newsletters).
 *
 * Routes:
 *   GET /api/{site}/crm/members/{memberId}/communications
 */
class CrmCommunicationsController extends Controller
{
    public function __construct(
        private readonly CrmMemberRepository        $crmMemberRepository,
        private readonly CommunicationLogRepository $communicationLogRepository,
    )
    {
        parent::__construct();
    }

    /**
     * GET /api/{site}/crm/members/{memberId}/communications
     *
     * Query params:
     *   type      string  all|transactional|marketing  (default: all)
     *   page      int     (default: 1)
     *   per_page  int     (default: 15, max: 50)
     *
     * Response shape:
     * {
     *   "communications": [
     *     {
     *       "id": 1,
     *       "type": "transactional",          // "transactional" | "marketing"
     *       "channel": "email",               // "email" | "sms" | ... future
     *       "subject": "Your receipt #1234",
     *       "preview": "Thank you for your…", // first ~100 chars, optional
     *       "status": "delivered",            // sent|delivered|opened|bounced|failed|unsubscribed
     *       "opened_at": "2025-04-01 10:22:00",  // null if not opened
     *       "sent_at": "2025-04-01 09:45:00",
     *       "template_name": "receipt",       // internal template identifier, optional
     *       "campaign_name": null             // populated for marketing type
     *     }
     *   ],
     *   "pagination": {
     *     "total": 42,
     *     "per_page": 15,
     *     "current_page": 1,
     *     "last_page": 3
     *   }
     * }
     */
    public function index(Request $request, int $memberId): JsonResponse
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $member = $this->crmMemberRepository->findForSite($memberId, SiteContext::getId());

        if (!$member) {
            return $this->errorResponse('Member not found', 404);
        }

        $type = $request->input('type', 'all');
        $page = max(1, (int)$request->input('page', 1));
        $perPage = min(50, max(1, (int)$request->input('per_page', 15)));

        $result = $this->communicationLogRepository->getPaginatedForMember(
            memberId: $memberId,
            type: in_array($type, ['transactional', 'marketing'], true) ? $type : null,
            page: $page,
            perPage: $perPage,
        );

        $rows = collect($result['data'])->map(fn($row) => [
            'id' => $row->id,
            'type' => $row->type,
            'channel' => $row->channel ?? 'email',
            'subject' => $row->subject,
            'preview' => $row->preview ?? null,
            'status' => $row->status,
            'opened_at' => $this->formatDateTime($row->opened_at ?? null),
            'sent_at' => $this->formatDateTime($row->sent_at ?? null),
            'template_name' => $row->template_name ?? null,
            'campaign_name' => $row->campaign_name ?? null,
        ])->all();

        return $this->resourceResponse([
            'communications' => $rows,
            'pagination' => [
                'total' => $result['total'],
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $result['last_page'],
            ],
        ]);
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (empty($value)) {
            return null;
        }

        $timestamp = strtotime((string) $value);

        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}
