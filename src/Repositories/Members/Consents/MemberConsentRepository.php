<?php

namespace App\Repositories\Members\Consents;

use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;
use App\Models\MemberConsent;

class MemberConsentRepository
{
    public function findByMemberAndType(int $memberId, int $consentTypeId): ?MemberConsent
    {
        return MemberConsent::where('member_id', $memberId)
            ->where('consent_type_id', $consentTypeId)
            ->first();
    }

    public function findAllByMember(int $memberId): Collection
    {
        return MemberConsent::where('member_id', $memberId)->get();
    }

    public function findExpired(): Collection
    {
        return MemberConsent::where('is_granted', true)
            ->where('expires_at', '<=', now_datetime()->format('Y-m-d H:i:s'))
            ->whereNull('revoked_at')
            ->get();
    }

    public function queryByType(int $consentTypeId): QueryBuilder
    {
        return MemberConsent::where('consent_type_id', $consentTypeId);
    }
}