<?php

namespace App\Services\Adverts;

use App\Enums\Adverts\RenderChannel;
use App\Enums\Adverts\SurfaceType;

class RenderContext
{
    public function __construct(
        public readonly ?int      $memberId,
        public readonly ?string   $plan,
        public readonly bool      $isPaid,
        public readonly string    $channel, // 'newsletter' | 'web'
        public readonly string    $surfaceType, // newsletter_issue or page
        public readonly int       $surfaceId, // newsletter_issue_id or page_id
        public readonly \DateTime $timestamp,
        public readonly ?int      $siteId = null
    )
    {
    }

    public static function forNewsletter(int $issueId, ?\App\Models\Member $member = null): self
    {
        return new self(
            $member?->id,
            $member?->subscription?->plan?->name, // or plan_id
            $member?->isPaid() ?? false,
            RenderChannel::NEWSLETTER->value,
            SurfaceType::NEWSLETTER_ISSUE->value,
            $issueId,
            now_datetime()
        );
    }

    public static function forWeb(int $pageId, ?\App\Models\Member $member = null): self
    {
        return new self(
            $member?->id,
            $member?->subscription?->plan?->name, // or plan_id
            $member?->isPaid() ?? false,
            RenderChannel::WEB->value,
            SurfaceType::PAGE->value,
            $pageId,
            now_datetime()
        );
    }
}