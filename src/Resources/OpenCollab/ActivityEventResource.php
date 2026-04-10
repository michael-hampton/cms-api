<?php

namespace App\Resources\OpenCollab;

use App\Framework\Resource\JsonResource;

class ActivityEventResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'type' => $this->getAttribute('type'),
            'payload' => json_decode($this->getAttribute('payload') ?? '{}', true),
            'user_id' => $this->getAttribute('user_id'),
            'site_id' => $this->getAttribute('site_id'),
            'created_at' => $this->getAttribute('created_at'),
            'label' => $this->humanLabel(),
        ];
    }

    private function humanLabel(): string
    {
        return match ($this->getAttribute('type')) {
            'article_created' => 'Created an article',
            'article_updated' => 'Updated an article',
            'article_published' => 'Published an article',
            'comment_added' => 'Added a comment',
            'invitation_sent' => 'Invitation sent',
            'invitation_accepted' => 'Invitation accepted',
            'payment_received' => 'Payment received',
            default => ucfirst(str_replace('_', ' ', $this->getAttribute('type') ?? '')),
        };
    }
}