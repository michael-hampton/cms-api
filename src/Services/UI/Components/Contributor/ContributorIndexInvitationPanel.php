<?php

namespace App\Services\UI\Components\Contributor;

use App\Services\UI\Components\ViewComponent;

final class ContributorIndexInvitationPanel extends ViewComponent
{
    public function key(): string
    {
        return 'contributors.invitation';
    }

    public function render(array $context = []): string
    {
        return $this->renderView('open-collab.admin.contributors.panels.index-invitation', $context);
    }
}
