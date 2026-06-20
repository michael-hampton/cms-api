<?php

namespace App\Services\Subscriptions;

use App\Models\Site;

final readonly class SubscriptionAccountContext
{
    private function __construct(
        public string $mode,
        public ?Site $site,
        public ?string $siteSlug,
        public bool $isSiteScoped,
        public bool $canAcquireSubscription,
        public bool $showSubscriptionModal,
        public string $theme,
        public string $loginUrl,
    ) {
    }

    public static function pressStack(): self
    {
        return new self(
            mode: 'press_stack',
            site: null,
            siteSlug: null,
            isSiteScoped: false,
            canAcquireSubscription: false,
            showSubscriptionModal: false,
            theme: 'press_stack',
            loginUrl: '/member/login',
        );
    }

    public static function memberArea(Site $site, string $siteSlug): self
    {
        return new self(
            mode: 'member',
            site: $site,
            siteSlug: $siteSlug,
            isSiteScoped: true,
            canAcquireSubscription: true,
            showSubscriptionModal: true,
            theme: 'member',
            loginUrl: '/' . $siteSlug . '/member/login',
        );
    }

    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
            'site' => $this->site,
            'site_slug' => $this->siteSlug,
            'is_site_scoped' => $this->isSiteScoped,
            'can_acquire_subscription' => $this->canAcquireSubscription,
            'show_subscription_modal' => $this->showSubscriptionModal,
            'theme' => $this->theme,
            'login_url' => $this->loginUrl,
        ];
    }
}
