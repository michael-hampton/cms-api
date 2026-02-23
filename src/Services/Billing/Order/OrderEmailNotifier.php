<?php

namespace App\Services\Billing\Order;

use App\Framework\Mail\MailManager;
use App\Framework\Support\Logger;
use App\Mail\Orders\OrderConfirmation;
use App\Models\Order;

class OrderEmailNotifier
{
    public function __construct(
        private readonly MailManager $mailManager
    )
    {
    }

    public function sendConfirmation(Order $order, ?string $customerEmail = null): bool
    {
        $email = $this->resolveEmail($order, $customerEmail);

        if (!$email) {
            return false;
        }

        try {
            $this->mailManager->to($email)->send(new OrderConfirmation($order));
            return true;
        } catch (\Exception $e) {
            Logger::error("Failed to send order confirmation email", [
                'order_id' => $order->id,
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return false;
            // Don't throw - email failure shouldn't fail order creation
        }
    }

    private function resolveEmail(Order $order, ?string $customerEmail): ?string
    {
        return $order->user?->email ?: $customerEmail;
    }
}