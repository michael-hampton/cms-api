<?php

namespace App\Services\PublicContent\Newsletter;

use App\DTO\PublicContent\NewsletterWidgetState;
use App\Models\Member;
use App\Repositories\Newsletters\SubscriberRepository;

/**
 * Resolves top-down newsletter island state for composition.
 *
 * "Already subscribed" is only true for authenticated members with an active
 * newsletter subscription. Anonymous visitors never receive a hardcoded
 * subscribed signal.
 */
final class NewsletterWidgetStateResolver
{
    public function __construct(
        private readonly SubscriberRepository $subscribers,
    ) {
    }

    public function resolve(
        int $siteId,
        string $siteSlug,
        ?Member $member,
        ?string $newsletterName = null,
        ?string $newsletterDescription = null,
    ): NewsletterWidgetState {
        $loginUrl = '/' . rawurlencode($siteSlug) . '/member/login';
        $manageUrl = '/' . rawurlencode($siteSlug) . '/member/newsletters';

        if ($member === null || empty($member->email)) {
            return new NewsletterWidgetState(
                authenticated: false,
                subscribed: false,
                loginUrl: $loginUrl,
                manageUrl: null,
                newsletterName: $newsletterName,
                newsletterDescription: $newsletterDescription,
            );
        }

        $activeIds = $this->subscribers->getActiveNewsletterIdsForMember(
            (string) $member->email,
            $siteId,
        );

        return new NewsletterWidgetState(
            authenticated: true,
            subscribed: $activeIds !== [],
            loginUrl: null,
            manageUrl: $manageUrl,
            newsletterName: $newsletterName,
            newsletterDescription: $newsletterDescription,
        );
    }
}
