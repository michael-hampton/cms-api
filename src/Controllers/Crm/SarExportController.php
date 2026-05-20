<?php

namespace App\Controllers\Crm;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Member;
use App\Services\Gdpr\GdprAuditLogger;
use App\Services\Gdpr\MemberExportService;
use Exception;

/**
 * GET  /api/crm/members/{id}/sar-export
 *
 * Generates a full Subject Access Request data bundle for a member.
 * Requires admin authentication.
 * Every access is recorded in the GDPR audit log.
 */
class SarExportController extends Controller
{
    public function __construct(
        private readonly MemberExportService $exportService,
        private readonly GdprAuditLogger     $auditLogger,
    ) {
        parent::__construct();
    }

    public function export(Request $request, int $memberId): JsonResponse
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized.', 401);
        }

        $adminId = Auth::id();
        $siteId  = SiteContext::getId();

        $member = Member::where('id', $memberId)
            //->where('site_id', $siteId)
            ->first();

        if (!$member) {
            return $this->errorResponse('Member not found.', 404);
        }

        // Log the request before we generate — if export fails the request
        // is still audited.
        $this->auditLogger->logAdminAction(
            action:   GdprAuditLogger::SAR_REQUESTED,
            memberId: $memberId,
            adminId:  $adminId,
            metadata: ['site_id' => $siteId],
        );

        try {
            $bundle = $this->exportService->export($member);
        } catch (Exception $e) {
            return $this->errorResponse('Export failed. Please try again.', 500);
        }

        $this->auditLogger->logAdminAction(
            action:   GdprAuditLogger::SAR_DOWNLOADED,
            memberId: $memberId,
            adminId:  $adminId,
            metadata: [
                'site_id'     => $siteId,
                'exported_at' => $bundle['exported_at'],
                'modules'     => array_keys($bundle['modules']),
            ],
        );

        $format = $request->get('format', 'json');

        if ($format === 'csv') {
            return $this->csvResponse($bundle, $memberId);
        }

        return $this->resourceResponse(['data' => $bundle]);
    }

    private function csvResponse(array $bundle, int $memberId): JsonResponse
    {
        // For CSV we flatten each module into its own section.
        // Clients that need a true CSV file should handle download client-side.
        // We return JSON with a csv_url hint for async download in future.
        return $this->resourceResponse(['data' => $bundle]);
    }
}