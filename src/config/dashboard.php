<?php

/**
 * Dashboard widget configuration.
 *
 * This file defines the system-wide default widget order and
 * permission-shaped overrides. User-level overrides are stored in the
 * contributor_dashboard_widgets table and applied at runtime by WidgetResolver.
 *
 * Merging precedence (lowest → highest):
 *   system default  →  permission default  →  user override (DB)
 *
 * Widget keys must match DashboardWidgetInterface::key() exactly.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | System Default Widget Order
    |--------------------------------------------------------------------------
    |
    | Used when no role config exists for the authenticated user.
    | Order is the default display position (0-indexed).
    |
    */
    'default' => [
        'onboarding',
        'drafts',
        'earnings',
        'activity',
        'quick_links',
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission-Based Widget Configuration
    |--------------------------------------------------------------------------
    |
    | When a role entry exists, it fully replaces the system default.
    | Add new roles without touching any controller or widget class.
    |
    */
    'permission_sets' => [
        'creator' => [
            'onboarding',
            'drafts',
            'earnings',
            'activity',
            'quick_links',
        ],
        'reviewer' => [
            'review_queue',
            'approvals',
            'activity',
        ],
        'finance' => [
            'activity',
            'quick_links',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Onboarding Gate
    |--------------------------------------------------------------------------
    |
    | Roles listed here will see ONLY the onboarding widget until their
    | onboarding is complete. This replaces the old separate onboarding
    | dashboard route/view/controller. Once OnboardingWidget::visibleFor()
    | returns false (onboarding done), the full widget set is shown.
    |
    */
    'onboarding_permission' => 'onboarding.view',

    'widget_permissions' => [
        'onboarding' => ['onboarding.view'],
        'drafts' => ['content.create', 'content.edit_own'],
        'earnings' => ['payout.request', 'ledger.view'],
        'activity' => [],
        'quick_links' => [],
        'review_queue' => ['content.review'],
        'approvals' => ['content.approve'],
    ],

];
