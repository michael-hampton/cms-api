<?php

namespace App\Services\Gdpr;

interface MemberDataCleanerInterface
{
    public function deleteAddresses(int $memberId): void;
    public function deleteNotes(int $memberId): void;
    public function deleteNotifications(int $memberId): void;
    public function revokeConsents(int $memberId): void;
    public function disableSubscriptions(int $memberId): void;
}