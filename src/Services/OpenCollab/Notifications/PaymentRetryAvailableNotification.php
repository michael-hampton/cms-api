<?php

namespace App\Services\OpenCollab\Notifications;

use App\Framework\Mail\Mailable;
use App\Framework\Notifications\EmailableNotification;
use App\Mail\OpenCollab\ArticlePaymentFailedMail;
use App\Models\ArticlePayment;
use App\Models\Page;

final class PaymentRetryAvailableNotification extends OpenCollabUserNotification
    implements EmailableNotification
{
    public function __construct(
        public readonly ArticlePayment $payment,
        public readonly Page           $page,
    )
    {
        parent::__construct(userId: $payment->user_id, email: $payment->email);
    }

    public function subject(): string
    {
        return "Ready to retry your payment for \"{$this->page->title}\"";
    }

    public function toMailable(): Mailable
    {
        // Retry and failure share the same mailable — the template
        // differentiates via the payment status field.
        return new ArticlePaymentFailedMail($this->payment, $this->page);
    }
}