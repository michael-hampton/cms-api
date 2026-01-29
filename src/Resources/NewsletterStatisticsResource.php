<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class NewsletterStatisticsResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'newsletter_id' => $this->getAttribute('newsletter_id'),
            'newsletter_name' => $this->getAttribute('newsletter_name'),
            'total_sends' => $this->getAttribute('total_sends'),
            'total_recipients' => $this->getAttribute('total_recipients'),
            'total_clicks' => $this->getAttribute('total_clicks'),
            'unique_clickers' => $this->getAttribute('unique_clickers'),
            'click_through_rate' => $this->getAttribute('click_through_rate'),
            'clicks_per_recipient' => $this->getAttribute('clicks_per_recipient'),
            'top_clicked_pages' => $this->getAttribute('top_clicked_pages'),
            'sends_by_date' => $this->getAttribute('sends_by_date'),
        ];
    }
}