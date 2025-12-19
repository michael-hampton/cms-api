<?php

namespace App\Services;

use App\Framework\Support\SiteContext;
use App\Repositories\NewsletterRepository;
use App\Repositories\SubscriberRepository;

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

    public function signup(string $email, bool $autoConfirm = false, ?int $newsletterId = null, ?int $siteId = null): array
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

        $existing = $this->repository->findByEmailAndNewsletter($email, $newsletterId, $siteId);

        if ($existing) {
            if ($existing->isConfirmed()) {
                return ['success' => false, 'error' => 'Email already subscribed'];
            }
            return ['success' => false, 'error' => 'Confirmation pending'];
        }

        $confirmationToken = $this->generateToken($email, 'confirm');
        $unsubscribeToken = $this->generateToken($email, 'unsubscribe');

        $subscriber = $this->repository->create([
            'email' => $email,
            'newsletter_id' => $newsletterId,
            'confirmed' => $autoConfirm,
            'confirmation_token' => $confirmationToken,
            'unsubscribe_token' => $unsubscribeToken,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $siteId
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
        $this->repository->delete($subscriber->id);

        return ['success' => true, 'email' => $email];
    }

    public function unsubscribeById(int $subscriberId, int $siteId): array
    {
        $subscriber = $this->repository->find($subscriberId);

        if (!$subscriber || $subscriber->site_id !== $siteId) {
            return ['success' => false, 'error' => 'Subscriber not found'];
        }

        $email = $subscriber->email;
        $this->repository->delete($subscriber->id);

        return ['success' => true, 'email' => $email];
    }

    public function getConfirmedSubscribers(?int $siteId = null): array
    {
        $siteId = $siteId ?? SiteContext::getId();
        return $this->repository->getConfirmedEmails($siteId);
    }
}