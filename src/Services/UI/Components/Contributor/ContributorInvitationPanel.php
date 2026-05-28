<?php

namespace App\Services\UI\Components\Contributor;

use App\Services\UI\Components\ViewComponent;

final class ContributorInvitationPanel extends ViewComponent
{
    public function key(): string
    {
        return 'contributor.invitation';
    }

    public function render(array $context = []): string
    {
        return $this->renderView('open-collab.admin.contributors.panels.invitation', $context);
    }
}
