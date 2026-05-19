<?php

/**
 * Dashboard widget configuration.
 *
 * This file defines the system-wide default widget order and
 * role-specific overrides. User-level overrides are stored in the
 * contributor_dashboard_widgets table and applied at runtime by WidgetResolver.
 *
 * Merging precedence (lowest → highest):
 *   system default  →  role default  →  user override (DB)
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
    | Role-Based Widget Configuration
    |--------------------------------------------------------------------------
    |
    | When a role entry exists, it fully replaces the system default.
    | Add new roles without touching any controller or widget class.
    |
    */
    'roles' => [
        'contributor' => [
            'onboarding',
            'drafts',
            'earnings',
            'activity',
            'quick_links',
        ],

        'editor' => [
            'review_queue',
            'approvals',
            'activity',
        ],

        // Add more roles here as new modules are introduced.
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
    'onboarding_gated_roles' => [
        'contributor',
    ],

];