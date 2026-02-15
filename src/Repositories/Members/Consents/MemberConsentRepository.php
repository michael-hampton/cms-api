<?php

namespace App\Repositories\Members\Consents;

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
            ->where('expires_at', '<=', now())
            ->whereNull('revoked_at')
            ->get();
    }

    public function queryByType(int $consentTypeId)
    {
        return MemberConsent::where('consent_type_id', $consentTypeId);
    }

    /**
     * Create a new MemberConsent instance (not yet saved)
     */
    public function createNew(array $attributes = []): MemberConsent
    {
        $consent = new MemberConsent();

        foreach ($attributes as $key => $value) {
            $consent->{$key} = $value;
        }

        return $consent;
    }

    /**
     * Save a MemberConsent instance
     */
    public function save(MemberConsent $consent): bool
    {
        return $consent->save();
    }
}