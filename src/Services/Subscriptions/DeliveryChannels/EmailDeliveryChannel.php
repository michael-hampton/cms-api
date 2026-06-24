<?php

namespace App\Services\Subscriptions\DeliveryChannels;

use App\Framework\Notifications\MailableNotification;
use App\Framework\Notifications\NotificationDispatcher;
use App\Mail\IssueDeliveryMail;
use App\Models\IssueDelivery;
use App\Models\Newsletter;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Services\Newsletter\NewsletterContentBuilder;
use App\Services\Subscriptions\DeliveryChannelInterface;

class EmailDeliveryChannel implements DeliveryChannelInterface
{
    public function __construct(
        private readonly NewsletterContentBuilder $contentBuilder,
        private readonly NotificationDispatcher   $notificationDispatcher,
        private readonly NewsletterRepository     $newsletterRepository
    ) {
    }

    public function send(Subscription $subscription, IssueDelivery $issueDelivery): void
    {
        $member = $subscription->member(true)->first();

        if (!$member || !$member->email) {
            throw new \RuntimeException(
                "Cannot deliver issue {$issueDelivery->id}: member or email not found for subscription {$subscription->id}"
            );
        }

        $plan = $issueDelivery->subscriptionPlans()->first();

        if (!$plan) {
            throw new \RuntimeException(
                "Cannot deliver issue {$issueDelivery->id}: no subscription plan found"
            );
        }

        $newsletter = $this->resolveNewsletterFromPlan($plan);

        if (!$newsletter) {
            throw new \RuntimeException(
                "Cannot deliver issue {$issueDelivery->id}: subscription plan has no newsletter premium access grant"
            );
        }

        $accessResult = $subscription->canAccessNewsletter($newsletter, $member);

        if (!$accessResult->allowed) {
            throw new \RuntimeException(
                "Cannot deliver issue {$issueDelivery->id}: access denied — {$accessResult->reason}"
            );
        }

        $contentResult = $this->contentBuilder->build($newsletter, $issueDelivery->site_id, false, $member);

        if (!$contentResult['success']) {
            throw new \RuntimeException(
                "Cannot deliver issue {$issueDelivery->id}: content build failed — " . ($contentResult['error'] ?? 'unknown error')
            );
        }

        $html = str_replace(
            '{{UNSUBSCRIBE_LINK}}',
            $this->buildUnsubscribeUrl($member),
            $contentResult['html']
        );

        $subject = $issueDelivery->issue_title;

        if (empty($subject)) {
            $subject = $newsletter->title;
        }

        $mailable = new IssueDeliveryMail($member->email, $subject, $html);
        $sent = $this->notificationDispatcher->dispatch(new MailableNotification(
            mailable: $mailable,
            email: $member->email,
            subject: $subject,
            userId: (int) $member->id,
        ));

        if ($sent === 0) {
            throw new \RuntimeException(
                "Cannot deliver issue {$issueDelivery->id}: email notification dispatcher returned zero successful channels"
            );
        }
    }

    private function resolveNewsletterFromPlan(SubscriptionPlan $plan): ?Newsletter
    {
        foreach ($plan->getPremiumAccessGrants() as $grant) {
            if ($grant['type'] !== 'newsletter') {
                continue;
            }

            $newsletter = $this->newsletterRepository->findBySlugAndSite(
                $grant['identifier'],
                $plan->site_id
            );

            if ($newsletter) {
                return $newsletter;
            }
        }

        return null;
    }

    private function buildUnsubscribeUrl(object $member): string
    {
        return rtrim(config('app.url'), '/')
            . '/newsletter/unsubscribe?email='
            . urlencode($member->email);
    }
}
