<?php

namespace App\Services\Newsletter;

use App\Framework\Support\Logger;
use App\Models\Newsletter;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Subscriptions\MemberSubscriptionPreferenceRepository;
use App\Repositories\Subscriptions\SubscriberRepository;

class NewsletterRecipientResolver
{
    public function __construct(
        private readonly SubscriberRepository                   $subscriberRepository,
        private readonly MemberSubscriptionPreferenceRepository $preferenceRepository,
        private readonly MemberRepository                       $memberRepository
    )
    {
    }

    public function resolveForNewsletter(Newsletter $newsletter, int $siteId): array
    {
        // Get legacy subscribers
        $legacySubscribers = $this->subscriberRepository->getConfirmedEmails($siteId);

        // Get member-based subscribers (already filtered by newsletter frequency)
        $memberPreferences = $this->preferenceRepository->getActiveSubscribersForSite($siteId);

        $filteredMembers = $memberPreferences->filter(function ($pref) use ($newsletter) {
            return $pref->newsletter_frequency === $newsletter->interval
                && !($pref->newsletter_opt_out ?? false); // Check explicit opt-out flag
        });

        $memberEmails = $filteredMembers->map(fn($pref) => $pref->member->email)->toArray();

        return array_unique(array_merge($legacySubscribers, $memberEmails));
    }

    public function filterRecipients(array $emails, Newsletter $newsletter, int $siteId): array
    {
        $valid = [];
        $skipped = [];

        // Bulk fetch all members and preferences
        $members = $this->memberRepository->findByEmails($emails, $siteId);
        $membersByEmail = [];
        foreach ($members as $member) {
            $membersByEmail[$member->email] = $member;
        }

        $preferences = $this->preferenceRepository->findByEmails($emails, $siteId);
        $preferencesByEmail = [];
        foreach ($preferences as $pref) {
            $preferencesByEmail[$pref->member->email] = $pref;
        }

        foreach ($emails as $email) {
            $member = $membersByEmail[$email] ?? null;
            $preference = $preferencesByEmail[$email] ?? null;

            if ($member) {
                // Check global newsletter preference
                if (!$member->getCommunicationPreference('newsletter', true)) {
                    $skipped[$email] = 'Newsletter preference disabled in global settings';
                    continue;
                }

                // Check global marketing preference
                if (!$member->wantsMarketingEmails()) {
                    $skipped[$email] = 'Marketing emails disabled in global settings';
                    continue;
                }

                // Check newsletter-specific opt-out
                if ($preference && ($preference->newsletter_opt_out ?? false)) {
                    $skipped[$email] = 'Opted out of this newsletter';
                    continue;
                }
            }

            $valid[] = $email;
        }

        return [
            'valid' => $valid,
            'skipped' => $skipped
        ];
    }
}