<?php

namespace App\Services;

use App\Repositories\SubscriberRepository;

class NewsletterSignupService
{
    private SubscriberRepository $repository;
    private int $siteId;

    public function __construct(SubscriberRepository $repository, int $siteId)
    {
        $this->repository = $repository;
        $this->siteId = $siteId;
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

    public function signup(string $email): array
    {
        if (!$this->validateEmail($email)) {
            return ['success' => false, 'error' => 'Invalid email format'];
        }

        $existing = $this->repository->findByEmail($email, $this->siteId);

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
            'confirmed' => false,
            'confirmation_token' => $confirmationToken,
            'unsubscribe_token' => $unsubscribeToken,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        return [
            'success' => true,
            'email' => $email,
            'confirmation_token' => $confirmationToken
        ];
    }

    public function confirm(string $token): array
    {
        $subscriber = $this->repository->findByConfirmationToken($token);

        if (!$subscriber || $subscriber->site_id !== $this->siteId) {
            return ['success' => false, 'error' => 'Invalid confirmation token'];
        }

        $this->repository->update($subscriber->id, ['confirmed' => true]);

        return ['success' => true, 'email' => $subscriber->email];
    }

    public function unsubscribe(string $token): array
    {
        $subscriber = $this->repository->findByUnsubscribeToken($token);

        if (!$subscriber || $subscriber->site_id !== $this->siteId) {
            return ['success' => false, 'error' => 'Invalid unsubscribe token'];
        }

        $email = $subscriber->email;
        $this->repository->delete($subscriber->id);

        return ['success' => true, 'email' => $email];
    }

    public function getConfirmedSubscribers(): array
    {
        return $this->repository->getConfirmedEmails($this->siteId);
    }
}