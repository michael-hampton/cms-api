<?php

namespace App\Mail\OpenCollab;

use App\Framework\Mail\Mailable;
use App\Models\ArticlePayment;
use App\Models\Page;

/**
 * Sent to the buyer after a successful article payment.
 * The recipient may be a guest (no user account), so the email
 * comes from the payment record rather than a User model.
 */
class ArticlePaymentSucceededMail extends Mailable
{
    public function __construct(
        private readonly ArticlePayment $payment,
        private readonly Page           $page,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        return $this
            ->subject("Your access to \"{$this->page->title}\" is confirmed")
            ->markdown('emails.open-collab.payment-succeeded', [
                'payment' => $this->payment,
                'page' => $this->page,
                'amount' => '£' . number_format($this->payment->amount / 100, 2),
                'articleUrl' => $this->buildArticleUrl(),
            ]);
    }

    private function buildArticleUrl(): string
    {
        return rtrim(config('app.url'), '/') . '/articles/' . $this->page->slug;
    }
}