<?php

namespace App\Services;

use App\Framework\Http\Request;
use App\Models\ConsentAuditLog;
use App\Models\ConsentType;
use App\Models\ConsentWithdrawalRequest;
use App\Models\Member;
use App\Models\MemberConsent;

class ConsentService
{
    /**
     * Update multiple consents at once
     */
    public function updateConsents(
        Member   $member,
        array    $consents,
        string   $channel = 'web',
        ?Request $request = null
    ): array
    {
        $results = [];

        foreach ($consents as $consentCode => $granted) {
            try {
                if ($granted) {
                    $results[$consentCode] = $this->grantConsent(
                        $member,
                        $consentCode,
                        $channel,
                        $request
                    );
                } else {
                    $results[$consentCode] = $this->revokeConsent(
                        $member,
                        $consentCode,
                        $channel,
                        $request
                    );
                }
            } catch (\Exception $e) {
                $results[$consentCode] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Grant consent for a member
     */
    public function grantConsent(
        Member   $member,
        string   $consentCode,
        string   $channel = 'web',
        ?Request $request = null,
        ?array   $metadata = null
    ): MemberConsent
    {
        $consentType = $this->getConsentType($consentCode);

        $consent = MemberConsent::where('member_id', $member->id)
            ->where('consent_type_id', $consentType->id)
            ->first();

        $previousState = $consent ? $consent->is_granted : null;

        if (!$consent) {
            $consent = new MemberConsent();
            $consent->member_id = $member->id;
            $consent->consent_type_id = $consentType->id;
        }

        $consent->is_granted = true;
        $consent->channel = $channel;
        $consent->granted_at = now();
        $consent->revoked_at = null;
        $consent->created_at = now();

        // Calculate expiry if retention period is set
        if ($consentType->retention_days) {
            $consent->expires_at = now_datetime()->modify("+{$consentType->retention_days} days")->format('Y-m-d H:i:s');
        }

        if ($request) {
            $consent->ip_address = $request->ip();
            $consent->user_agent = $request->userAgent();
        }

        if ($metadata) {
            $consent->metadata = $metadata;
        }

        $consent->save();

        // Log to audit trail
        $this->logConsentChange(
            $member,
            $consentType,
            'granted',
            $previousState,
            true,
            $channel,
            $request
        );

        return $consent;
    }

    /**
     * Helper: Get consent type by code
     */
    private function getConsentType(string $code): ConsentType
    {
        $consentType = ConsentType::where('code', $code)
            ->where('is_active', true)
            ->first();

        if (!$consentType) {
            throw new \RuntimeException('Consent type not found: ' . $code);
        }

        return $consentType;
    }

    /**
     * Helper: Log consent change to audit trail
     */
    private function logConsentChange(
        Member      $member,
        ConsentType $consentType,
        string      $action,
        ?bool       $previousState,
        bool        $newState,
        string      $source,
        ?Request    $request = null,
        ?string     $reason = null,
        ?int        $adminUserId = null
    ): void
    {
        $log = new ConsentAuditLog();
        $log->member_id = $member->id;
        $log->consent_type_id = $consentType->id;
        $log->action = $action;
        $log->previous_state = $previousState;
        $log->new_state = $newState;
        $log->source = $source;
        $log->reason = $reason;
        $log->admin_user_id = $adminUserId;
        $log->created_at = now();

        if ($request) {
            $log->ip_address = $request->ip();
            $log->user_agent = $request->userAgent();
        }

        $log->save();
    }

    /**
     * Revoke consent for a member
     */
    public function revokeConsent(
        Member   $member,
        string   $consentCode,
        string   $source = 'web',
        ?Request $request = null,
        ?string  $reason = null
    ): bool
    {
        $consentType = $this->getConsentType($consentCode);

        // Cannot revoke required consents
        if ($consentType->isRequired()) {
            throw new \RuntimeException('Cannot revoke required consent: ' . $consentCode);
        }

        $consent = MemberConsent::where('member_id', $member->id)
            ->where('consent_type_id', $consentType->id)
            ->first();

        if (!$consent) {
            return false;
        }

        $previousState = $consent->is_granted;

        $consent->is_granted = false;
        $consent->revoked_at = now();
        $consent->save();

        // Log to audit trail
        $this->logConsentChange(
            $member,
            $consentType,
            'revoked',
            $previousState,
            false,
            $source,
            $request,
            $reason
        );

        return true;
    }

    /**
     * Check if member has specific consent
     */
    public function hasConsent(Member $member, string $consentCode): bool
    {
        $consentType = ConsentType::where('code', $consentCode)
            ->where('is_active', true)
            ->first();

        if (!$consentType) {
            return false;
        }

        // Required consents are always granted
        if ($consentType->isRequired()) {
            return true;
        }

        $consent = MemberConsent::where('member_id', $member->id)
            ->where('consent_type_id', $consentType->id)
            ->first();

        return $consent && $consent->isActive();
    }

    /**
     * Get all member consents with their status
     */
    public function getMemberConsents(Member $member): array
    {
        $allConsentTypes = ConsentType::where('is_active', true)->get();
        $memberConsents = MemberConsent::where('member_id', $member->id)->get();

        $consentsMap = [];
        foreach ($memberConsents as $consent) {
            $consentsMap[$consent->consent_type_id] = $consent;
        }

        $result = [];
        foreach ($allConsentTypes as $type) {
            $memberConsent = $consentsMap[$type->id] ?? null;

            $result[] = [
                'consent_type' => [
                    'id' => $type->id,
                    'code' => $type->code,
                    'name' => $type->name,
                    'description' => $type->description,
                    'category' => $type->category,
                    'required' => $type->isRequired(),
                    'retention_days' => $type->retention_days
                ],
                'is_granted' => $memberConsent ? $memberConsent->isActive() : false,
                'granted_at' => $memberConsent?->granted_at,
                'expires_at' => $memberConsent?->expires_at,
                'channel' => $memberConsent?->channel
            ];
        }

        return $result;
    }

    /**
     * Get consent audit trail for a member
     */
    public function getAuditTrail(Member $member, ?string $consentCode = null): array
    {
        $query = ConsentAuditLog::where('member_id', $member->id)
            ->orderBy('created_at', 'desc');

        if ($consentCode) {
            $consentType = $this->getConsentType($consentCode);
            $query->where('consent_type_id', $consentType->id);
        }

        return $query->with(['consentType', 'adminUser'])
            ->get()
            ->toArray();
    }

    /**
     * Process expired consents
     */
    public function processExpiredConsents(): int
    {
        $expiredCount = 0;

        $expiredConsents = MemberConsent::where('is_granted', true)
            ->where('expires_at', '<=', now_datetime()->format('Y-m-d H:i:s'))
            ->whereNull('revoked_at')
            ->get();

        foreach ($expiredConsents as $consent) {
            $consent->is_granted = false;
            $consent->revoked_at = now();
            $consent->save();

            // Log expiry
            $this->logConsentChange(
                $consent->member,
                $consent->consentType,
                'expired',
                true,
                false,
                'system'
            );

            $expiredCount++;
        }

        return $expiredCount;
    }

    /**
     * Create consent withdrawal request
     */
    public function createWithdrawalRequest(
        Member $member,
        string $type,
        ?array $consentTypes = null
    ): ConsentWithdrawalRequest
    {
        $request = new ConsentWithdrawalRequest();
        $request->member_id = $member->id;
        $request->type = $type;
        $request->consent_types = $consentTypes;
        $request->status = 'pending';
        $request->requested_at = now();
        $request->save();

        return $request;
    }

    /**
     * Process consent withdrawal request
     */
    public function processWithdrawalRequest(
        ConsentWithdrawalRequest $request,
        int                      $adminUserId
    ): bool
    {
        if (!$request->isPending()) {
            return false;
        }

        $member = $request->member;

        try {
            switch ($request->type) {
                case 'specific_consent':
                    foreach ($request->consent_types as $consentCode) {
                        $this->revokeConsent(
                            $member,
                            $consentCode,
                            'admin',
                            null,
                            'Withdrawal request processed'
                        );
                    }
                    break;

                case 'all_marketing':
                    $marketingConsents = ConsentType::where('category', 'marketing')
                        ->where('is_active', true)
                        ->get();

                    foreach ($marketingConsents as $consentType) {
                        $this->revokeConsent(
                            $member,
                            $consentType->code,
                            'admin',
                            null,
                            'All marketing consent withdrawal'
                        );
                    }
                    break;

                case 'complete_deletion':
                    // This would trigger full data deletion process
                    // Implementation depends on your data deletion policies
                    break;
            }

            $request->status = 'completed';
            $request->completed_at = now();
            $request->processed_by = $adminUserId;
            $request->save();

            return true;
        } catch (\Exception $e) {
            $request->status = 'cancelled';
            $request->notes = 'Error: ' . $e->getMessage();
            $request->save();

            throw $e;
        }
    }

    /**
     * Get consent statistics for reporting
     */
    public function getConsentStatistics(?int $siteId = null): array
    {
        $stats = [];

        $consentTypes = ConsentType::where('is_active', true)->get();

        foreach ($consentTypes as $type) {
            $query = MemberConsent::where('consent_type_id', $type->id);

            if ($siteId) {
                $query->whereHas('member', function ($q) use ($siteId) {
                    $q->where('site_id', $siteId);
                });
            }

            $total = $query->count();
            $granted = $query->where('is_granted', true)->count();
            $active = $query->where('is_granted', true)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->count();

            $stats[$type->code] = [
                'name' => $type->name,
                'category' => $type->category,
                'total_records' => $total,
                'granted' => $granted,
                'active' => $active,
                'grant_rate' => $total > 0 ? round(($granted / $total) * 100, 2) : 0
            ];
        }

        return $stats;
    }
}