<?php

namespace App\Services\Newsletter;

use App\Enums\Newsletters\CommunicationChannel;
use App\Framework\Support\Logger;
use App\Models\Newsletter;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Newsletters\SubscriberRepository;
use App\Repositories\Subscriptions\MemberSubscriptionPreferenceRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

class NewsletterRecipientResolver
{
    public function __construct(
        private readonly SubscriberRepository                   $subscriberRepository,
        private readonly MemberSubscriptionPreferenceRepository $preferenceRepository,
        private readonly MemberRepository       $memberRepository,
        private readonly SubscriptionRepository $subscriptionRepository
    )
    {
    }

    public function resolveForNewsletter(Newsletter $newsletter, int $siteId): array
    {
        // Phase 1: Eligibility (business rules, money-related, non-negotiable)
        $eligible = $this->resolveEligibleRecipients($newsletter, $siteId);

        // Phase 2: Preferences (opt-outs, marketing settings, user choice)
        $filtered = $this->applyPreferences($eligible, $newsletter, $siteId);

        return [
            'valid' => $filtered['valid'],
            'skipped' => $filtered['skipped']
        ];
    }

    /**
     * Phase 1: Determine who is ALLOWED to receive the newsletter
     * This is purely entitlement-based
     */
    private function resolveEligibleRecipients(Newsletter $newsletter, int $siteId): array
    {
        // Free newsletter: everyone eligible
        if (!$newsletter->isPremium()) {
            return $this->resolveAllRecipients($newsletter, $siteId);
        }

        // Paid newsletter: members only, with valid subscriptions
        return $this->resolvePaidEligibleRecipients($newsletter, $siteId);
    }

    /**
     * Get all recipients for free newsletters
     */
    private function resolveAllRecipients(Newsletter $newsletter, int $siteId): array
    {
        // Legacy subscribers
        $legacyEmails = $this->subscriberRepository->getConfirmedEmails($siteId);

        // Member-based subscribers
        $memberPreferences = $this->preferenceRepository->getActiveSubscribersForSite($siteId);

        $filteredMembers = $memberPreferences->filter(function ($pref) use ($newsletter) {
            return $pref->newsletter_frequency === $newsletter->interval
                && !($pref->newsletter_opt_out ?? false);
        });

        $memberEmails = $filteredMembers->map(fn($pref) => $pref->member->email)->toArray();

        return array_unique(array_merge($legacyEmails, $memberEmails));
    }

    /**
     * Get eligible recipients for paid newsletters
     */
    private function resolvePaidEligibleRecipients(Newsletter $newsletter, int $siteId): array
    {
        $eligible = [];
        $newsletterSlug = $newsletter->slug;

        // Get member preferences matching frequency
        $memberPreferences = $this->preferenceRepository->getActiveSubscribersForSite($siteId);

        $matchingPreferences = $memberPreferences->filter(function ($pref) use ($newsletter) {
            return $pref->newsletter_frequency === $newsletter->interval
                && !($pref->newsletter_opt_out ?? false);
        });

        foreach ($matchingPreferences as $pref) {
            $member = $pref->member;
            $email = $member->email;

            // Get active subscription for member
            $subscription = $this->subscriptionRepository->getActiveSubscriptionForMember($member->id, $siteId);

            if (!$subscription) {
                Logger::warning('Paid newsletter excluded: no active subscription', [
                    'email' => $email,
                    'newsletter' => $newsletter->title,
                    'newsletter_slug' => $newsletterSlug
                ]);
                continue;
            }

            $accessResult = $subscription->canAccessNewsletter($newsletter, $member);

            if (!$accessResult->allowed) {
                Logger::warning('Paid newsletter excluded', array_merge([
                    'email' => $email,
                    'newsletter' => $newsletter->title,
                    'subscription_plan' => $subscription->plan_name,
                ], $accessResult->toArray()));
                continue;
            }

            // Eligible
            $eligible[] = $email;
        }

        // Critical: log if paid newsletter has zero eligible recipients
        if (empty($eligible)) {
            Logger::error('Paid newsletter has zero eligible recipients', [
                'newsletter' => $newsletter->title,
                'newsletter_slug' => $newsletterSlug,
                'site_id' => $siteId
            ]);
        }

        return array_unique($eligible);
    }

    /**
     * Phase 2: Apply preference filtering
     * This phase can only exclude, never add
     */
    private function applyPreferences(array $eligibleEmails, Newsletter $newsletter, int $siteId): array
    {
        $valid = [];
        $skipped = [];

        // Bulk fetch
        $members = $this->memberRepository->findByEmails($eligibleEmails, $siteId);
        $membersByEmail = [];
        foreach ($members as $member) {
            $membersByEmail[$member->email] = $member;
        }

        $preferences = $this->preferenceRepository->findByEmails($eligibleEmails, $siteId);
        $preferencesByEmail = [];
        foreach ($preferences as $pref) {
            $preferencesByEmail[$pref->member->email] = $pref;
        }

        foreach ($eligibleEmails as $email) {
            $member = $membersByEmail[$email] ?? null;
            $preference = $preferencesByEmail[$email] ?? null;

            // If not a member, they're a legacy subscriber - allow through
            if (!$member) {
                $valid[] = $email;
                continue;
            }

            // Check global newsletter preference using enum
            if (!$member->getCommunicationPreference(CommunicationChannel::Newsletter->value, true)) {
                $skipped[$email] = 'Newsletter preference disabled in global settings';
                continue;
            }

            // Check global marketing preference using enum
            if (!$member->getCommunicationPreference(CommunicationChannel::Marketing->value, true)) {
                $skipped[$email] = 'Marketing emails disabled in global settings';
                continue;
            }

            // Check newsletter-specific opt-out
            if ($preference && ($preference->newsletter_opt_out ?? false)) {
                $skipped[$email] = 'Opted out of this newsletter';
                continue;
            }

            $valid[] = $email;
        }

        return [
            'valid' => $valid,
            'skipped' => $skipped
        ];
    }


    /**
     * Legacy compatibility method
     * @deprecated Use resolveForNewsletter() instead
     */
    public function filterRecipients(array $emails, Newsletter $newsletter, int $siteId): array
    {
        return $this->applyPreferences($emails, $newsletter, $siteId);
    }
}