<?php

namespace App\Services;

use App\Framework\Mail\MailManager;
use App\Framework\Support\Logger;
use App\Mail\DealAlert;
use App\Mail\ForgotPassword;
use App\Mail\MemberSignupConfirmation;
use App\Mail\NewsletterSignupConfirmationWithTrackingTest;
use App\Mail\NewsletterWelcome;
use App\Mail\PriceAlert;
use App\Mail\ResetPassword;
use App\Models\EventSignup;
use App\Models\Member;
use App\Models\Product;

class EmailService
{
    private MailManager $mailManager;

    public function __construct(?MailManager $mailManager = null)
    {
        $this->mailManager = $mailManager ?? MailManager::getInstance();
    }

    public function sendEventConfirmation(EventSignup $signup, string $eventTitle): bool
    {
        // In a real implementation, you'd use a proper email service
        // For now, we'll simulate the email sending

        $subject = "Event Registration Confirmation - {$eventTitle}";
        $body = "
            Dear {$signup->name},
            
            Thank you for registering for {$eventTitle}.
            
            Event Details:
            - Date: {$signup->event_date->format('l, F jS, Y')}
            - Name: {$eventTitle}
            
            We'll send you a reminder closer to the event date.
            
            Best regards,
            Premier Properties Team
        ";

        // Simulate email sending
        return mail($signup->email, $subject, $body);
    }

    public function notifyNewComment($comment): bool
    {
        // Notify administrators of new comments
        return true;
    }

    public function send(string $to, string $subject, string $html): bool
    {
        // Mock implementation - replace with actual email provider
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: noreply@example.com'
        ];

        return mail($to, $subject, $html, implode("\r\n", $headers));
    }

    public function sendPriceAlert(
        Product $product,
        Member  $member,
        float   $oldPrice,
        float   $newPrice,
        float   $targetPrice
    ): bool
    {
        try {
            return $this->mailManager
                ->to($member->email)
                ->send(new PriceAlert($product, $member, $oldPrice, $newPrice, $targetPrice));
        } catch (\Exception $e) {
            Logger::error('Failed to send price alert email', [
                'member_id' => $member->id,
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function sendDealAlert(Member $member, array $deals): bool
    {
        try {
            return $this->mailManager
                ->to($member->email)
                ->send(new DealAlert($member, $deals));
        } catch (\Exception $e) {
            Logger::error('Failed to send deal alert email', [
                'member_id' => $member->id,
                'deal_count' => count($deals),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function sendSignupConfirmation(Member $member, string $verificationToken): bool
    {
        try {
            return $this->mailManager
                ->to($member->email)
                ->send(new MemberSignupConfirmation($member, $verificationToken));
        } catch (\Exception $e) {
            Logger::error('Failed to send signup confirmation email', [
                'member_id' => $member->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function sendPasswordReset(Member $member, string $resetToken, int $expiresInMinutes = 60): bool
    {
        try {
            return $this->mailManager
                ->to($member->email)
                ->send(new ResetPassword($member, $resetToken, $expiresInMinutes));
        } catch (\Exception $e) {
            Logger::error('Failed to send password reset email', [
                'member_id' => $member->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function sendForgotPassword(string $email, string $resetToken, int $expiresInMinutes = 60): bool
    {
        try {
            return $this->mailManager
                ->to($email)
                ->send(new ForgotPassword($email, $resetToken, $expiresInMinutes));
        } catch (\Exception $e) {
            Logger::error('Failed to send forgot password email', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function sendNewsletterConfirmation(
        string  $email,
        string  $confirmationToken,
        ?string $firstName = null,
        array   $preferences = []
    ): bool
    {
        try {
            return $this->mailManager
                ->to($email)
                ->send(new NewsletterSignupConfirmationWithTrackingTest($email, $confirmationToken, $firstName, $preferences));
        } catch (\Exception $e) {
            Logger::error('Failed to send newsletter confirmation email', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function sendNewsletterWelcome(
        string  $email,
        ?string $firstName = null,
        ?array  $welcomeOffer = null
    ): bool
    {
        try {
            return $this->mailManager
                ->to($email)
                ->send(new NewsletterWelcome($email, $firstName, $welcomeOffer));
        } catch (\Exception $e) {
            Logger::error('Failed to send newsletter welcome email', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
