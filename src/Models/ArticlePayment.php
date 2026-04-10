<?php

namespace App\Models;

use App\Enums\OpenCollab\PaymentStatus;

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
    ];

    public function hasSucceeded(): bool
    {
        return $this->status === PaymentStatus::Succeeded->value;
    }

    public function hasFailed(): bool
    {
        return $this->status === PaymentStatus::Failed->value;
    }
}