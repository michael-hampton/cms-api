<?php

namespace App\Services\Members\Consents;

use App\DTO\Consents\ConsentStatisticsDTO;
use App\DTO\Consents\MemberConsentSnapshot;
use App\Models\Member;
use App\Repositories\Members\Consents\ConsentTypeRepository;
use App\Repositories\Members\Consents\MemberConsentRepository;

class ConsentQueryService
{
    public function __construct(
        private readonly ConsentTypeRepository   $consentTypeRepository,
        private readonly MemberConsentRepository $memberConsentRepository
    )
    {
    }

    public function hasConsent(Member $member, string $consentCode): bool
    {
        $consentType = $this->consentTypeRepository->findActiveByCode($consentCode);

        if (!$consentType) {
            return false;
        }

        if ($consentType->isRequired()) {
            return true;
        }

        $consent = $this->memberConsentRepository->findByMemberAndType(
            $member->id,
            $consentType->id
        );

        return $consent && $consent->isActive();
    }

    public function getMemberConsents(Member $member): array
    {
        $allConsentTypes = $this->consentTypeRepository->findAllActive();
        $memberConsents = $this->memberConsentRepository->findAllByMember($member->id);

        $consentsMap = [];
        foreach ($memberConsents as $consent) {
            $consentsMap[$consent->consent_type_id] = $consent;
        }

        $result = [];
        foreach ($allConsentTypes as $type) {
            $memberConsent = $consentsMap[$type->id] ?? null;

            $result[] = new MemberConsentSnapshot(
                consentTypeId: $type->id,
                code: $type->code,
                name: $type->name,
                description: $type->description,
                category: $type->category,
                required: $type->isRequired(),
                retentionDays: $type->retention_days,
                isGranted: $memberConsent ? $memberConsent->isActive() : false,
                grantedAt: $memberConsent?->granted_at,
                expiresAt: $memberConsent?->expires_at,
                channel: $memberConsent?->channel
            );
        }

        return array_map(fn($snapshot) => $snapshot->toArray(), $result);
    }

    public function getAuditTrail(Member $member, ?string $consentCode = null): array
    {
        $query = \App\Models\ConsentAuditLog::where('member_id', $member->id)
            ->orderBy('created_at', 'desc');

        if ($consentCode) {
            $consentType = $this->consentTypeRepository->findActiveByCode($consentCode);
            $query->where('consent_type_id', $consentType->id);
        }

        return $query->with(['consentType', 'adminUser'])
            ->get()
            ->toArray();
    }

    public function getConsentStatistics(?int $siteId = null): array
    {
        $consentTypes = $this->consentTypeRepository->findAllActive();
        $stats = [];

        foreach ($consentTypes as $type) {
            $query = $this->memberConsentRepository->queryByType($type->id);

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

            $dto = new ConsentStatisticsDTO(
                code: $type->code,
                name: $type->name,
                category: $type->category,
                totalRecords: $total,
                granted: $granted,
                active: $active,
                grantRate: $total > 0 ? round(($granted / $total) * 100, 2) : 0
            );

            $stats[$type->code] = $dto->toArray();
        }

        return $stats;
    }
}