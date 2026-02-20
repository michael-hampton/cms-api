<?php

namespace App\Services\Subscriptions;

use App\Enums\Newsletters\CommunicationChannel;
use App\Framework\Support\Collection;
use App\Models\Member;
use App\Models\MemberSubscriptionPreference;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Subscriptions\MemberSubscriptionPreferenceRepository;
use App\Repositories\Subscriptions\SubscriberRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

class MemberSubscriptionService
{
    public function __construct(
        private readonly MemberRepository                       $memberRepository,
        private readonly MemberSubscriptionPreferenceRepository $preferenceRepository,
        private readonly SubscriberRepository   $subscriberRepository,
        private readonly SubscriptionRepository $subscriptionRepository
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

    /**
     * Update global communication preferences
     */
    public function updateCommunicationPreferences(int $memberId, array $preferences): ?Member
    {
        $member = $this->memberRepository->find($memberId);

        if (!$member) {
            return null;
        }

        $allowedKeys = [
            'marketing_emails',
            'special_offers',
            'third_party_communications',
            'product_updates',
            'newsletter'
        ];

        $filtered = array_intersect_key($preferences, array_flip($allowedKeys));

        // Convert to boolean values
        foreach ($filtered as $key => $value) {
            $filtered[$key] = (bool)$value;
        }

        $member->updateCommunicationPreferences($filtered);

        return $member;
    }

    /**
     * Check if member should receive marketing email based on preferences
     */
    public function shouldReceiveMarketingEmail(Member $member, string $emailType = 'marketing'): bool
    {
        // Always send transactional emails
        if ($emailType === 'transactional') {
            return true;
        }

        // Check general marketing preference
        if (!$member->wantsMarketingEmails()) {
            return false;
        }

        // Check specific email types
        return match ($emailType) {
            'special_offer' => $member->wantsSpecialOffers(),
            'third_party' => $member->wantsThirdPartyCommunications(),
            'product_update' => $member->getCommunicationPreference(CommunicationChannel::ProductUpdates->value, true),
            'newsletter' => $member->getCommunicationPreference(CommunicationChannel::Newsletter->value, true),
            default => $member->wantsMarketingEmails(),
        };
    }

    /**
     * Update the auto-renewal setting for a member's subscription.
     *
     * Auto-renewal is a member-facing preference that controls whether the
     * subscription renews automatically at the end of the billing period.
     * consent_given must be explicitly true when enabling to satisfy audit
     * requirements — we record that the member actively opted in.
     *
     * This method only updates the DB record. If the subscription has a Stripe
     * subscription attached, the caller is responsible for syncing Stripe separately
     * (out of scope for the initial implementation — Stripe cancel_at_period_end
     * can be wired in here later without changing the contract).
     *
     * @throws \InvalidArgumentException If the subscription does not belong to the member.
     * @throws \RuntimeException         If consent is not given when enabling auto-renewal.
     */
    public function updateAutoRenew(int $subscriptionId, int $memberId, bool $autoRenew, bool $consentGiven): array
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->member_id !== $memberId) {
            throw new \InvalidArgumentException('Subscription not found');
        }

        if ($autoRenew && !$consentGiven) {
            throw new \RuntimeException('Consent is required to enable auto-renewal');
        }

        $this->subscriptionRepository->update($subscriptionId, [
            'auto_renew' => $autoRenew,
            'consent_given' => $autoRenew ? true : $subscription->consent_given,
        ]);

        return [
            'success' => true,
            'auto_renew' => $autoRenew,
        ];
    }
}