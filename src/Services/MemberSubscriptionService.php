<?php

namespace App\Services;

use App\Framework\Support\Collection;
use App\Models\Member;
use App\Models\MemberSubscriptionPreference;
use App\Repositories\MemberRepository;
use App\Repositories\MemberSubscriptionPreferenceRepository;
use App\Repositories\SubscriberRepository;

class MemberSubscriptionService
{
    public function __construct(
        private readonly MemberRepository                       $memberRepository,
        private readonly MemberSubscriptionPreferenceRepository $preferenceRepository,
        private readonly SubscriberRepository                   $subscriberRepository
    )
    {
    }

    public function updatePreferences(int $memberId, array $data, int $siteId): MemberSubscriptionPreference
    {
        $preferences = $this->preparePreferencesData($data);

        $preference = $this->preferenceRepository->updatePreferences($memberId, $preferences, $siteId);

        if (!$preference) {
            // Create if doesn't exist
            $preference = $this->preferenceRepository->getOrCreateForMember($memberId, $siteId);
            $preference->update($preferences);
        }

        return $preference;
    }

    private function preparePreferencesData(array $data): array
    {
        $prepared = [];

        if (isset($data['email_notifications'])) {
            $prepared['email_notifications'] = (bool)$data['email_notifications'];
        }

        if (isset($data['newsletter_frequency'])) {
            $prepared['newsletter_frequency'] = $data['newsletter_frequency'];
        }

        if (isset($data['content_types'])) {
            $prepared['content_types'] = is_array($data['content_types'])
                ? $data['content_types']
                : json_decode($data['content_types'], true);
        }

        if (isset($data['category_preferences'])) {
            $prepared['category_preferences'] = is_array($data['category_preferences'])
                ? $data['category_preferences']
                : json_decode($data['category_preferences'], true);
        }

        if (isset($data['is_active'])) {
            $prepared['is_active'] = (bool)$data['is_active'];
        }

        return $prepared;
    }

    public function subscribeMemberToNewsletters(Member $member, array $newsletterTypes, int $siteId): array
    {
        $results = [];

        foreach ($newsletterTypes as $type) {
            $existing = $this->subscriberRepository->findByEmail($member->email, $siteId);

            if ($existing) {
                $results[] = $existing;
                continue;
            }

            $subscriber = $this->subscriberRepository->create([
                'email' => $member->email,
                'confirmed' => true, // Auto-confirm for members
                'confirmation_token' => bin2hex(random_bytes(32)),
                'unsubscribe_token' => bin2hex(random_bytes(32)),
                'subscribed_at' => date('Y-m-d H:i:s'),
                'site_id' => $siteId
            ]);

            $results[] = $subscriber;
        }

        return $results;
    }

    public function unsubscribeByToken(string $token): bool
    {
        return $this->preferenceRepository->unsubscribe($token);
    }

    public function resubscribeByToken(string $token): bool
    {
        return $this->preferenceRepository->resubscribe($token);
    }

    public function getSubscriptionSummary(int $memberId, int $siteId): array
    {
        $preference = $this->getPreferencesForMember($memberId, $siteId);
        $member = $this->memberRepository->find($memberId);

        $newsletters = $this->getAllNewslettersForMember($member->email, $siteId);

        return [
            'preference' => $preference,
            'is_active' => $preference->is_active,
            'email_notifications' => $preference->email_notifications,
            'frequency' => $preference->newsletter_frequency,
            'content_types' => $preference->content_types ?? [],
            'category_preferences' => $preference->category_preferences ?? [],
            'newsletters_count' => $newsletters->count(),
            'unsubscribe_url' => $this->getUnsubscribeUrl($preference),
            'manage_url' => $this->getManageUrl($preference)
        ];
    }

    public function getPreferencesForMember(int $memberId, int $siteId): MemberSubscriptionPreference
    {
        return $this->preferenceRepository->getOrCreateForMember($memberId, $siteId);
    }

    public function getAllNewslettersForMember(string $email, int $siteId): Collection
    {
        return $this->subscriberRepository->getNewslettersForMember($email, $siteId);
    }

    public function getUnsubscribeUrl(MemberSubscriptionPreference $preference): string
    {
        return url("/member/subscriptions/unsubscribe/{$preference->unsubscribe_token}");
    }

    public function getManageUrl(MemberSubscriptionPreference $preference): string
    {
        return url("/member/subscriptions/manage/{$preference->unsubscribe_token}");
    }
}