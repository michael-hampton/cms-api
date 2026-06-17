<?php

namespace App\Services\PublicContent;

use App\Models\Page;
use App\Models\User;
use App\Services\OpenCollab\OpenCollabAuthorizationService;

final class EditorialPreviewAuthorizationService
{
    private const EDITOR_PERMISSIONS = [
        'content.edit',
        'content.review',
        'content.approve',
        'content.publish',
        'pages.edit',
        'pages.review',
        'pages.approve',
        'pages.publish',
    ];

    public function __construct(
        private readonly OpenCollabAuthorizationService $authorization,
    ) {
    }

    public function canPreview(User $user, Page $page, int $siteId): bool
    {
        if ((int) $page->site_id !== $siteId) {
            return false;
        }

        if ($this->authorization->allowsAny((int) $user->id, $siteId, self::EDITOR_PERMISSIONS)) {
            return true;
        }

        return $this->authorization->allows((int) $user->id, $siteId, 'content.edit_own')
            && (int) $page->contributor_id === (int) $user->id;
    }
}
