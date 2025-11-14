<?php

namespace App\Services;

use App\Models\DealAlert;
use App\Repositories\DealAlertRepository;

class DealAlertService
{
    private DealAlertRepository $repository;

    public function __construct(?DealAlertRepository $repository = null)
    {
        $this->repository = $repository ?? new DealAlertRepository();
    }

    public function subscribe(array $data): array
    {
        // Validate email
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Valid email is required'];
        }

        // Check for existing subscription
        $existing = $this->repository->findByEmail($data['email']);

        if ($existing) {
            if ($existing->isVerified()) {
                return [
                    'success' => false,
                    'message' => 'This email is already subscribed to deal alerts'
                ];
            } else {
                // Resend verification
                $this->sendVerificationEmail($existing);
                return [
                    'success' => true,
                    'message' => 'Verification email resent. Please check your inbox.'
                ];
            }
        }

        // Create new subscription
        $verificationToken = bin2hex(random_bytes(32));

        $alert = $this->repository->create([
            'member_id' => $data['user_id'] ?? null,
            'email' => $data['email'],
            'frequency' => $data['frequency'] ?? 'daily',
            'preferences' => $data['preferences'] ?? null,
            'is_active' => true,
            'verification_token' => $verificationToken
        ]);

        // Send verification email
        $this->sendVerificationEmail($alert);

        return [
            'success' => true,
            'message' => 'Subscription created! Please check your email to verify.',
            'alert' => $alert
        ];
    }

    public function verify(string $token): array
    {
        $alert = $this->repository->findByToken($token);

        if (!$alert) {
            return ['success' => false, 'message' => 'Invalid verification token'];
        }

        if ($alert->isVerified()) {
            return ['success' => false, 'message' => 'Email already verified'];
        }

        $this->repository->update($alert->id, [
            'verified_at' => date('Y-m-d H:i:s'),
            'verification_token' => null
        ]);

        return [
            'success' => true,
            'message' => 'Email verified successfully! You will now receive deal alerts.'
        ];
    }

    public function unsubscribe(string $email): array
    {
        $alert = $this->repository->findByEmail($email);

        if (!$alert) {
            return ['success' => false, 'message' => 'Subscription not found'];
        }

        $this->repository->update($alert->id, ['is_active' => false]);

        return [
            'success' => true,
            'message' => 'Successfully unsubscribed from deal alerts'
        ];
    }

    public function updatePreferences(string $email, array $preferences): array
    {
        $alert = $this->repository->findByEmail($email);

        if (!$alert) {
            return ['success' => false, 'message' => 'Subscription not found'];
        }

        $this->repository->update($alert->id, [
            'frequency' => $preferences['frequency'] ?? $alert->frequency,
            'preferences' => $preferences['preferences'] ?? $alert->preferences
        ]);

        return [
            'success' => true,
            'message' => 'Preferences updated successfully'
        ];
    }

    private function sendVerificationEmail(DealAlert $alert): bool
    {
        try {
            $verificationUrl = url("/deal-alerts/verify?token={$alert->verification_token}");

            $subject = "Verify Your Deal Alert Subscription";

            $htmlMessage = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: #232f3e; color: white; padding: 20px; text-align: center; }
                        .content { background: #f9f9f9; padding: 20px; }
                        .button { display: inline-block; background: #ff9900; color: #0f1111; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>🔔 Verify Your Deal Alert Subscription</h1>
                        </div>
                        <div class='content'>
                            <p>Thanks for subscribing to our deal alerts!</p>
                            <p>Please verify your email address by clicking the button below:</p>
                            <p style='text-align: center; margin: 30px 0;'>
                                <a href='{$verificationUrl}' class='button'>Verify Email</a>
                            </p>
                            <p>Or copy and paste this link into your browser:</p>
                            <p style='word-break: break-all; color: #007185;'>{$verificationUrl}</p>
                            <p style='font-size: 12px; color: #666; margin-top: 20px;'>
                                If you didn't subscribe to deal alerts, you can safely ignore this email.
                            </p>
                        </div>
                    </div>
                </body>
                </html>
            ";

            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: Deal Alerts <noreply@" . $host . ">\r\n";

            return mail($alert->email, $subject, $htmlMessage, $headers);
        } catch (\Exception $e) {
            error_log("Failed to send verification email: " . $e->getMessage());
            return false;
        }
    }

    public function getAlertStats(): array
    {
        return [
            'total_alerts' => $this->repository->getTotalCount(),
            'active_alerts' => $this->repository->getActiveCount(),
        ];
    }
}