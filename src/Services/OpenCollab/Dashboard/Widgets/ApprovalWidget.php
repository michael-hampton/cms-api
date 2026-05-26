<?php
namespace App\Services\OpenCollab\Dashboard\Widgets;

use App\Models\User;
use App\Repositories\Cms\Pages\PageRepository;
use App\Services\OpenCollab\Dashboard\Contracts\DashboardWidgetInterface;

final class ApprovalWidget implements DashboardWidgetInterface
{
    public function __construct(
        private readonly PageRepository $pageRepository,
    ) {}

    public function key(): string   { return 'approvals'; }
    public function title(): string { return 'Pending Approvals'; }

    public function visibleFor(User $user): bool
    {
        return true;
    }

    public function data(User $user): array
    {
        return [
            'items' => $this->pageRepository->getPendingApproval(assignedTo: $user->id)->toArray(),
        ];
    }
}
