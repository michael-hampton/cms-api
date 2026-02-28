<?php

namespace App\Services\Newsletter\DTOs;

use App\Enums\Newsletters\CommunicationChannel;
use App\Models\Member;
use App\Models\Newsletter;
use App\Services\Adverts\RenderContext;

class NewsletterRenderContext
{
    public function __construct(
        public readonly int        $siteId,
        public readonly Newsletter $newsletter,
        public readonly ?Member    $member,
        public readonly ?int       $sendId,
        public readonly bool       $includeTracking,
        public readonly string     $channel = CommunicationChannel::Newsletter->value
    )
    {
    }

    public function toAdvertContext(): RenderContext
    {
        return new RenderContext(
            $this->member?->id,
            $this->member?->subscription?->plan?->name,
            $this->member?->isPaid() ?? false,
            $this->channel,
            'newsletter_issue',
            $this->newsletter->id,
            now_datetime()
        );
    }

    public static function fromArray(array $context): self
    {
        return new self(
            siteId: $context['site_id'],
            newsletter: $context['newsletter'],
            member: $context['member'],
            sendId: $context['send_id'],
            includeTracking: $context['include_tracking']
        );
    }
}