<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class NewsletterIssueResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'newsletter_id' => $this->getAttribute('newsletter_id'),
            'site_id' => $this->getAttribute('site_id'),
            'issue_number' => $this->getAttribute('issue_number'),
            'subject' => $this->getAttribute('subject'),
            'content_blocks' => $this->getAttribute('content_blocks'),
            'snapshot_json' => $this->getAttribute('snapshot_json'),
            'html_snapshot' => $this->getAttribute('html_snapshot'),
            'status' => $this->getAttribute('status'),
            'send_id' => $this->getAttribute('send_id'),
            'scheduled_at' => $this->getAttribute('scheduled_at')?->format('Y-m-d H:i:s'),
            'sent_at' => $this->getAttribute('sent_at')?->format('Y-m-d H:i:s'),
            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d H:i:s'),
            'updated_at' => $this->getAttribute('updated_at')?->format('Y-m-d H:i:s'),
        ];
    }
}