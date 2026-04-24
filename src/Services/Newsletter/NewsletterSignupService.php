<?php

namespace App\Services\Newsletter;

use App\Framework\Support\SiteContext;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Newsletters\SubscriberRepository;

class NewsletterSignupService
{
    private int $siteId;

    public function __construct(
        private readonly NewsletterRepository $newsletterRepository,
        private readonly SubscriberRepository $repository
    )
    {

    }

    public function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function generateToken(string $email, string $type): string
    {
        $salt = bin2hex(random_bytes(16));
        return hash('sha256', $email . ':' . $type . ':' . $salt);
    }

    public function signup(
        string $email,
        bool   $autoConfirm = false,
        ?int   $newsletterId = null,
        ?int   $siteId = null,
        ?int   $campaignId = null
    ): array
    {
        $siteId = $siteId ?? SiteContext::getId();

        if (!$this->validateEmail($email)) {
            return ['success' => false, 'error' => 'Invalid email format'];
        }

        // If no newsletter ID provided, get the default
        if ($newsletterId === null) {
            $defaultNewsletter = $this->newsletterRepository->getDefaultNewsletterForSite($siteId);

            if (!$defaultNewsletter) {
                return ['success' => false, 'error' => 'No newsletter available for subscription'];
            }

            $newsletterId = $defaultNewsletter->id;
        }

        // Check for existing subscription (active or unsubscribed)
        $existing = $this->repository->findExisting($email, $newsletterId, $siteId);

        if ($existing) {
            // If they're currently subscribed and confirmed
            if ($existing->isActive() && $existing->isConfirmed()) {
                return ['success' => false, 'error' => 'Email already subscribed'];
            }

            // If they're currently subscribed but not confirmed
            if ($existing->isActive() && !$existing->isConfirmed()) {
                return ['success' => false, 'error' => 'Confirmation pending'];
            }

            // If they previously unsubscribed, resubscribe them
            if (!$existing->isActive()) {
                $existing->resubscribe($campaignId);
                $existing->update([
                    'confirmed' => $autoConfirm,
                    'subscribed_at' => date('Y-m-d H:i:s')
                ]);

                return [
                    'success' => true,
                    'email' => $email,
                    'newsletter_id' => $newsletterId,
                    'confirmation_token' => $existing->confirmation_token,
                    'subscriber_id' => $existing->id,
                    'resubscribed' => true
                ];
            }
        }

        // Create new subscription
        $confirmationToken = $this->generateToken($email, 'confirm');
        $unsubscribeToken = $this->generateToken($email, 'unsubscribe');

        $subscriber = $this->repository->create([
            'email' => $email,
            'newsletter_id' => $newsletterId,
            'confirmed' => $autoConfirm,
            'confirmation_token' => $confirmationToken,
            'unsubscribe_token' => $unsubscribeToken,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'unsubscribed_at' => null,
            'site_id' => $siteId,
            'campaign_id' => $campaignId
        ]);

        return [
            'success' => true,
            'email' => $email,
            'newsletter_id' => $newsletterId,
            'confirmation_token' => $confirmationToken,
            'subscriber_id' => $subscriber->id
        ];
    }

    public function confirm(string $token, ?int $siteId = null): array
    {
        $siteId = $siteId ?? SiteContext::getId();
        $subscriber = $this->repository->findByConfirmationToken($token);

        if (!$subscriber || $subscriber->site_id !== $siteId) {
            return ['success' => false, 'error' => 'Invalid confirmation token'];
        }

        $this->repository->update($subscriber->id, ['confirmed' => true]);

        return ['success' => true, 'email' => $subscriber->email];
    }

    public function unsubscribe(string $token, ?int $siteId = null): array
    {
        $siteId = $siteId ?? SiteContext::getId();
        $subscriber = $this->repository->findByUnsubscribeToken($token);

        if (!$subscriber || $subscriber->site_id !== $siteId) {
            return ['success' => false, 'error' => 'Invalid unsubscribe token'];
        }

        $email = $subscriber->email;
        $success = $this->repository->unsubscribe($subscriber->id);

        if (!$success) {
            return ['success' => false, 'error' => 'Failed to unsubscribe'];
        }

        return ['success' => true, 'email' => $email];
    }

    public function unsubscribeById(int $subscriberId, int $siteId): array
    {
        $subscriber = $this->repository->find($subscriberId);

        if (!$subscriber || $subscriber->site_id !== $siteId) {
            return ['success' => false, 'error' => 'Subscriber not found'];
        }

        $email = $subscriber->email;
        $success = $this->repository->unsubscribe($subscriberId);

        if (!$success) {
            return ['success' => false, 'error' => 'Failed to unsubscribe'];
        }

        return ['success' => true, 'email' => $email];
    }

    public function getConfirmedSubscribers(?int $siteId = null): array
    {
        $siteId = $siteId ?? SiteContext::getId();
        return $this->repository->getConfirmedEmails($siteId);
    }
}