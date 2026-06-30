<?php

namespace App\Listeners\OpenCollab;

use App\Events\OpenCollab\ArticleSubmittedForReviewEvent;
use App\Events\OpenCollab\DisputeRaisedEvent;
use App\Repositories\UserNotificationRepository;
use App\Services\BaseUserNotificationListener;
use App\Services\OpenCollab\SitePermissionResolver;

class SendArticleSentForReviewNotification extends BaseUserNotificationListener
{
    private const ADMIN_PERMISSIONS = [
        'payout.view',
        'payout.approve',
        'payout.reject',
        'ledger.view',
        'site.manage',
    ];

    public function __construct(
        \App\Services\UserNotificationService $service,
        \App\Repositories\OpenCollab\UserSiteRepository $userSiteRepository,
        private readonly SitePermissionResolver $permissionResolver,
        private readonly UserNotificationRepository $notifications,
    )
    {
        parent::__construct($service, $userSiteRepository);
    }

    public function handle(ArticleSubmittedForReviewEvent $event): void
    {
        $this->notify(
            $event->contributorId,
            'dispute_raised',
            [
                'page_id' => $event->page->id,
                'site_id' => $event->page->site_id,
            ],
            'contributor.dispute_raised'
        );

        if ($event->page->site_id === null) {
            return;
        }

        foreach ($this->adminUserIds($event->page->site_id, $event->contributorId) as $adminUserId) {
            $this->notifications->create(
                userId: $adminUserId,
                type: 'open_collab_article_sent_for_approval',
                data: [
                    'title' => 'New earnings dispute raised',
                    'message' => 'A contributor has raised an earnings dispute for review.',
                    'site_id' => $event->page->site_id,
                    'page_id' => $event->page->id,
                    'contributor_user_id' => $event->contributorId,
                    'url' => '/open-collab/admin/articles',
                ],
            );
        }
    }

    /**
     * @return array<int, int>
     */
    private function adminUserIds(int $siteId, int $contributorUserId): array
    {
        return array_values(array_filter(
            $this->userIdsForSite($siteId),
            fn(int $userId): bool => $this->canReceiveAdminDisputeAlert($userId, $siteId),
        ));
    }

    private function canReceiveAdminDisputeAlert(int $userId, int $siteId): bool
    {
        foreach (self::ADMIN_PERMISSIONS as $permission) {
            if ($this->permissionResolver->allows($userId, $siteId, $permission)) {
                return true;
            }
        }

        return false;
    }
}
