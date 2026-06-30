<?php

namespace App\Services\OpenCollab\Notifications;

use App\Framework\Mail\Mailable;
use App\Framework\Notifications\EmailableNotification;
use App\Mail\OpenCollab\ArticlePaymentSucceededMail;
use App\Models\ArticlePayment;
use App\Models\Page;
use App\Models\User;

final class ArticlePaymentSucceededNotification extends OpenCollabUserNotification
    implements EmailableNotification
{
    public function __construct(
        public readonly ArticlePayment $payment,
        public readonly Page           $page,
        public readonly ?User          $buyer = null,
    )
    {
        parent::__construct(
            userId: $buyer?->id,
            email: $payment->email,
        );
    }

    public function subject(): string
    {
        return "Your access to \"{$this->page->title}\" is confirmed";
    }

    public function toMailable(): Mailable
    {
        return new ArticlePaymentSucceededMail($this->payment, $this->page);
    }
}