<?php

namespace App\Models;

use App\Enums\OpenCollab\PaymentStatus;

/**
 * Represents a payment attempt for a paid article.
 *
 * Retry tracking columns (added via migration):
 *   attempt_count   int default 0
 *   last_attempt_at datetime nullable
 *   failure_reason  varchar nullable
 *
 * The model is unchanged from its existing shape except for documenting
 * the new fields and exposing helper methods used by PaymentRetryService.
 */
class ArticlePayment extends Model
{
    protected $table = 'oc_article_payments';

    protected $fillable = [
        'site_id',
        'page_id',
        'user_id',
        'email',
        'stripe_payment_intent_id',
        'status',
        'amount',
        'currency',
        'attempt_count',
        'last_attempt_at',
        'failure_reason',
    ];

    protected $appends = [
        'title'
    ];

    protected $casts = [
        'created_at' => 'date',
        'updated_at' => 'date',
        'last_attempt_at' => 'date',
    ];

    private const MAX_RETRY_ATTEMPTS = 3;

    public function hasSucceeded(): bool
    {
        return $this->status === PaymentStatus::Succeeded->value;
    }

    public function hasFailed(): bool
    {
        return $this->status === PaymentStatus::Failed->value;
    }

    /**
     * Whether this payment can be retried by the user.
     * Stripe retry endpoint only works on requires_payment_method status, but
     * we guard retry eligibility here based on our own attempt_count.
     */
    public function isRetryable(): bool
    {
        return $this->hasFailed()
            && (int)($this->attempt_count ?? 0) < self::MAX_RETRY_ATTEMPTS;
    }

    public function hasReachedMaxRetries(): bool
    {
        return (int)($this->attempt_count ?? 0) >= self::MAX_RETRY_ATTEMPTS;
    }
}