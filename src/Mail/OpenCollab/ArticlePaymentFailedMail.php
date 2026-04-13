<?php

namespace App\Mail\OpenCollab;

use App\Framework\Mail\Mailable;
use App\Models\ArticlePayment;
use App\Models\Page;

/**
 * Sent when a payment fails or when a retry becomes available.
 * The view uses $payment->status to adjust the message tone:
 *   'failed'  → payment failed, here's how to retry
 *   otherwise → a retry is ready
 */
class ArticlePaymentFailedMail extends Mailable
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
        $isFailed = ($this->payment->status ?? 'failed') === 'failed';
        $subject = $isFailed
            ? "Payment failed — try again to access \"{$this->page->title}\""
            : "Ready to retry your payment for \"{$this->page->title}\"";

        return $this
            ->subject($subject)
            ->markdown('emails.open-collab.payment-failed', [
                'payment' => $this->payment,
                'page' => $this->page,
                'amount' => '£' . number_format($this->payment->amount / 100, 2),
                'isFailed' => $isFailed,
                'retryUrl' => $this->buildRetryUrl(),
                'articleUrl' => $this->buildArticleUrl(),
            ]);
    }

    private function buildRetryUrl(): string
    {
        return rtrim(config('app.url'), '/') . '/articles/' . $this->page->slug;
    }

    private function buildArticleUrl(): string
    {
        return rtrim(config('app.url'), '/') . '/articles/' . $this->page->slug;
    }
}