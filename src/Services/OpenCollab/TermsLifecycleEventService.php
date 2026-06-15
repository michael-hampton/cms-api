<?php

namespace App\Services\OpenCollab;

use App\Events\OpenCollab\TermsVersionAcceptedEvent;
use App\Events\OpenCollab\TermsVersionPublishedEvent;
use App\Framework\Events\EventDispatcher;
use App\Models\TermsVersion;
use App\Models\UserTermsAcceptance;

class TermsLifecycleEventService
{
    public function __construct(private readonly EventDispatcher $events)
    {
    }

    public function published(TermsVersion $terms, int $publishedByUserId): void
    {
        $this->events->dispatch(new TermsVersionPublishedEvent(
            termsVersionId: (int)$terms->id,
            siteId: (int)$terms->site_id,
            publishedByUserId: $publishedByUserId,
            requiresReacceptance: (bool)$terms->is_material_change,
        ));
    }

    public function accepted(UserTermsAcceptance $acceptance): void
    {
        $this->events->dispatch(new TermsVersionAcceptedEvent(
            termsVersionId: (int)$acceptance->terms_version_id,
            siteId: (int)$acceptance->site_id,
            userId: (int)$acceptance->user_id,
            acceptedVia: (string)$acceptance->accepted_via,
        ));
    }
}
