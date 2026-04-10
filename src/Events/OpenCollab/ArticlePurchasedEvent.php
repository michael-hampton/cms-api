<?php

namespace App\Events\OpenCollab;

use App\Models\ArticlePayment;

/**
 * Fired by ArticleAccessService after access is confirmed and written.
 *
 * Listener: App\Listeners\OpenCollab\RecordEarningsOnPurchase
 * — Updates the contributor's running earnings total.
 *
 * Note: This event fires AFTER access has been granted, not at payment creation.
 * The webhook is the trigger; the listener is the side-effect.
 */
class ArticlePurchasedEvent
{
    public function __construct(
        public readonly ArticlePayment $payment,
        public readonly int            $pageId,
        public readonly int            $contributorId,
    )
    {
    }
}