<?php

namespace App\Services\Gdpr;

use App\Models\Address;
use App\Models\Member;
use App\Models\MemberConsent;
use App\Models\MemberNote;
use App\Models\MemberSubscriptionPreference;
use App\Models\Notification;

class MemberDataCleaner implements MemberDataCleanerInterface
{
    public function deleteAddresses(int $memberId): void
    {
        Address::where('member_id', $memberId)->delete();
    }

    public function deleteNotes(int $memberId): void
    {
        MemberNote::where('member_id', $memberId)->delete();
    }

    public function deleteNotifications(int $memberId): void
    {
        Notification::where('member_id', $memberId)->delete();
    }

    public function revokeConsents(int $memberId): void
    {
        MemberConsent::where('member_id', $memberId)
            ->where('is_granted', true)
            ->update([
                'is_granted' => false,
                'revoked_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function disableSubscriptions(int $memberId): void
    {
        MemberSubscriptionPreference::where('member_id', $memberId)
            ->update([
                'is_active'           => false,
                'email_notifications' => false,
                'newsletter_opt_out'  => true,
            ]);
    }
}