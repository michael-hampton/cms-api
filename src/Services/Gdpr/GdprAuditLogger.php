<?php

namespace App\Services\Gdpr;

use App\Framework\Http\Request;
use App\Models\GdprAuditLog;

/**
 * Writes immutable audit entries for all GDPR-sensitive operations.
 *
 * Every write is fire-and-forget from the service layer's perspective.
 * Failures are caught and logged to the system logger rather than
 * propagating — a failed audit write must never abort a member-facing flow.
 */
class GdprAuditLogger
{
    // ── Action constants ───────────────────────────────────────────────────

    public const SAR_REQUESTED   = 'sar_export_requested';
    public const SAR_DOWNLOADED  = 'sar_export_downloaded';
    public const RTBF_REQUESTED  = 'rtbf_requested';
    public const RTBF_EXECUTED   = 'rtbf_executed';
    public const ADMIN_OVERRIDE  = 'admin_override';
    public const FAILED_ACCESS   = 'failed_access_attempt';

    public function __construct(
        private readonly ?Request $request = null,
    ) {}

    // ── Public API ─────────────────────────────────────────────────────────

    public function logAdminAction(
        string $action,
        int    $memberId,
        int    $adminId,
        array  $metadata = [],
    ): void {
        $this->write(
            memberId:         $memberId,
            action:           $action,
            performedByType:  'admin',
            performedById:    $adminId,
            metadata:         $metadata,
        );
    }

    public function logMemberAction(
        string $action,
        int    $memberId,
        array  $metadata = [],
    ): void {
        $this->write(
            memberId:         $memberId,
            action:           $action,
            performedByType:  'member',
            performedById:    $memberId,
            metadata:         $metadata,
        );
    }

    public function logSystemAction(
        string $action,
        int    $memberId,
        array  $metadata = [],
    ): void {
        $this->write(
            memberId:         $memberId,
            action:           $action,
            performedByType:  'system',
            performedById:    null,
            metadata:         $metadata,
        );
    }

    // ── Private ────────────────────────────────────────────────────────────

    private function write(
        int     $memberId,
        string  $action,
        string  $performedByType,
        ?int    $performedById,
        array   $metadata = [],
    ): void {
        try {
            GdprAuditLog::create([
                'member_id'         => $memberId,
                'action'            => $action,
                'performed_by_type' => $performedByType,
                'performed_by_id'   => $performedById,
                'ip_address'        => $this->resolveIp(),
                'metadata'          => $metadata ?: null,
                'created_at'        => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Non-critical — audit failures must not block the primary operation
            error_log('[GdprAuditLogger] Failed to write audit log: ' . $e->getMessage());
        }
    }

    private function resolveIp(): ?string
    {
        if ($this->request === null) {
            return $_SERVER['REMOTE_ADDR'] ?? null;
        }

        return $this->request->ip() ?? $_SERVER['REMOTE_ADDR'] ?? null;
    }
}