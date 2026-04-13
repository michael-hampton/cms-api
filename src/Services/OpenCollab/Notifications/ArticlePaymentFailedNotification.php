<?php

namespace App\Services\OpenCollab\Notifications;

use App\Framework\Mail\Mailable;
use App\Framework\Notifications\AbstractNotification;
use App\Framework\Notifications\EmailableNotification;
use App\Mail\OpenCollab\ArticlePaymentFailedMail;
use App\Models\ArticlePayment;
use App\Models\Page;

final class ArticlePaymentFailedNotification extends AbstractNotification
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
        return "Payment failed — try again to access \"{$this->page->title}\"";
    }

    public function toMailable(): Mailable
    {
        return new ArticlePaymentFailedMail($this->payment, $this->page);
    }
}