<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

/**
 * Shapes a MemberSubscriptionPreference for API responses.
 *
 * Intentionally omits the raw unsubscribe_token from list/detail views —
 * it is a security-sensitive value that should only appear in dedicated
 * unsubscribe-link generation endpoints.
 */
class MemberSubscriptionPreferenceResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'member_id' => $this->getAttribute('member_id'),
            'site_id' => $this->getAttribute('site_id'),
            'is_active' => (bool)$this->getAttribute('is_active'),
            'email_notifications' => (bool)$this->getAttribute('email_notifications'),
            'newsletter_frequency' => $this->getAttribute('newsletter_frequency'),
            'content_types' => $this->getAttribute('content_types') ?? [],
            'category_preferences' => $this->getAttribute('category_preferences') ?? [],
            'newsletter_opt_out' => (bool)($this->getAttribute('newsletter_opt_out') ?? false),
            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d H:i:s'),
            'updated_at' => $this->getAttribute('updated_at')?->format('Y-m-d H:i:s'),
        ];
    }
}