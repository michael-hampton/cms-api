<?php

namespace App\Services\Cms;

use App\Services\OpenCollab\OpenCollabAuthorizationService;

class ContentWorkflowAuthorizationService
{
    public function __construct(
        private readonly OpenCollabAuthorizationService $authorization,
    ) {
    }

    public function assertCanRequestApproval(int $userId, int $siteId): void
    {
        $this->authorization->assert($userId, $siteId, 'content.submit', 'Missing permission: content.submit');
    }

    public function assertCanApprove(int $userId, int $siteId): void
    {
        $this->authorization->assert($userId, $siteId, 'content.approve', 'Missing permission: content.approve');
    }

    public function assertCanReject(int $userId, int $siteId): void
    {
        $this->authorization->assert($userId, $siteId, 'content.reject', 'Missing permission: content.reject');
    }
}
