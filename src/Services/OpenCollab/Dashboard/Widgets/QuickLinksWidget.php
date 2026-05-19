<?php

namespace App\Services\OpenCollab\Dashboard\Widgets;

use App\Models\User;
use App\Services\OpenCollab\Dashboard\Contracts\DashboardWidgetInterface;

/**
 * Quick links widget — contextual navigation shortcuts.
 *
 * Migrated from the sidebar in open-collab/dashboard/show.php which
 * contained hard-coded links to: earnings & payouts, profile settings,
 * and account closure. The links are static configuration; no repository
 * access is needed.
 *
 * Data shape:
 * {
 *   links: [{ label, href, variant: 'default'|'danger' }]
 * }
 *
 * The JS renderer controls icons and presentation.
 * variant = 'danger' drives red colouring (close account link).
 */
final class QuickLinksWidget implements DashboardWidgetInterface
{
    public function key(): string
    {
        return 'quick_links';
    }

    public function title(): string
    {
        return 'Quick Links';
    }

    public function visibleFor(User $user): bool
    {
        return true;
    }

    public function data(User $user): array
    {
        return [
            'links' => [
                [
                    'label'   => 'Earnings & payouts',
                    'href'    => '/contributor/earnings',
                    'variant' => 'default',
                ],
                [
                    'label'   => 'Profile settings',
                    'href'    => '/contributor/settings',
                    'variant' => 'default',
                ],
                [
                    'label'   => 'Close account',
                    'href'    => '/contributor/settings#danger',
                    'variant' => 'danger',
                ],
            ],
        ];
    }
}