<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Framework\Support\SiteContext;
use App\Models\MemberSubscriptionPreference;

class MemberSubscriptionPreferenceRepository extends Repository
{
    public function getOrCreateForMember(int $memberId, ?int $siteId = null): MemberSubscriptionPreference
    {
        $siteId = $siteId ?? SiteContext::getId();

        $preference = $this->findByMember($memberId, $siteId);

        if ($preference) {
            return $preference;
        }

        $token = bin2hex(random_bytes(32));

        return MemberSubscriptionPreference::create([
            'member_id' => $memberId,
            'site_id' => $siteId,
            'email_notifications' => true,
            'newsletter_frequency' => 'weekly',
            'content_types' => null,
            'category_preferences' => null,
            'unsubscribe_token' => $token,
            'is_active' => true
        ]);
    }

    public function findByMember(int $memberId, ?int $siteId = null): ?MemberSubscriptionPreference
    {
        $siteId = $siteId ?? SiteContext::getId();

        return MemberSubscriptionPreference::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->first();
    }

    public function updatePreferences(int $memberId, array $preferences, ?int $siteId = null): ?MemberSubscriptionPreference
    {
        $siteId = $siteId ?? SiteContext::getId();

        $preference = $this->findByMember($memberId, $siteId);

        if (!$preference) {
            return null;
        }

        $preference->update($preferences);
        return $preference;
    }

    public function unsubscribe(string $token): bool
    {
        $preference = $this->findByToken($token);

        if (!$preference) {
            return false;
        }

        return $preference->unsubscribe();
    }

    public function findByToken(string $token): ?MemberSubscriptionPreference
    {
        return MemberSubscriptionPreference::where('unsubscribe_token', $token)->first();
    }

    public function resubscribe(string $token): bool
    {
        $preference = $this->findByToken($token);

        if (!$preference) {
            return false;
        }

        return $preference->resubscribe();
    }

    public function getActiveSubscribersForSite(?int $siteId = null): Collection
    {
        $siteId = $siteId ?? SiteContext::getId();

        return MemberSubscriptionPreference::where('site_id', $siteId)
            ->where('is_active', true)
            ->where('email_notifications', true)
            ->get();
    }

    public function getSubscribersForContentType(string $contentType, ?int $siteId = null): Collection
    {
        $siteId = $siteId ?? SiteContext::getId();

        $all = MemberSubscriptionPreference::where('site_id', $siteId)
            ->where('is_active', true)
            ->where('email_notifications', true)
            ->get();

        return $all->filter(function ($preference) use ($contentType) {
            // If no specific content types set, they want all content
            if (!$preference->content_types) {
                return true;
            }
            // Check if content type is in their preferences
            return in_array($contentType, $preference->content_types);
        });
    }

    public function getSubscribersForCategory(int $categoryId, ?int $siteId = null): Collection
    {
        $siteId = $siteId ?? SiteContext::getId();

        $all = MemberSubscriptionPreference::where('site_id', $siteId)
            ->where('is_active', true)
            ->where('email_notifications', true)
            ->get();

        return $all->filter(function ($preference) use ($categoryId) {
            // If no specific categories set, they want all categories
            if (!$preference->category_preferences) {
                return true;
            }
            // Check if category is in their preferences
            return in_array($categoryId, $preference->category_preferences);
        });
    }

    protected function getModelClass(): string
    {
        return MemberSubscriptionPreference::class;
    }
}