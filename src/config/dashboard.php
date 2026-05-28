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

    'components' => [
        [
            'key' => 'contributors.invitation',
            'type' => 'page_panel',
            'surface' => 'contributors.index',
            'label' => 'Invite Contributor',
            'capabilities' => ['creator.invite'],
            'component' => \App\Services\UI\Components\Contributor\ContributorIndexInvitationPanel::class,
            'sort_order' => 10,
            'enabled' => true,
        ],
        [
            'key' => 'contributor.details',
            'type' => 'page_panel',
            'surface' => 'contributor.show',
            'label' => 'Contributor Details',
            'capabilities' => ['contributor.details.view'],
            'component' => \App\Services\UI\Components\Contributor\ContributorDetailsPanel::class,
            'sort_order' => 10,
            'enabled' => true,
        ],
        [
            'key' => 'contributor.invitation',
            'type' => 'page_panel',
            'surface' => 'contributor.show',
            'label' => 'Invitation',
            'capabilities' => ['contributor.invitation.view'],
            'component' => \App\Services\UI\Components\Contributor\ContributorInvitationPanel::class,
            'sort_order' => 20,
            'enabled' => true,
        ],
        [
            'key' => 'contributor.capabilities',
            'type' => 'page_panel',
            'surface' => 'contributor.show',
            'label' => 'Capabilities',
            'capabilities' => ['contributor.capabilities.view'],
            'component' => \App\Services\UI\Components\Contributor\ContributorCapabilitiesPanel::class,
            'sort_order' => 30,
            'enabled' => true,
        ],
        [
            'key' => 'contributor.invitation_action',
            'type' => 'page_action',
            'surface' => 'contributor.show',
            'label' => 'Send Invitation',
            'capabilities' => ['creator.invite'],
            'component' => 'ContributorInvitationAction',
            'sort_order' => 40,
            'enabled' => true,
        ],
        [
            'key' => 'contributor.violation_action',
            'type' => 'page_action',
            'surface' => 'contributor.show',
            'label' => 'View Violations',
            'capabilities' => ['violation.view'],
            'component' => 'ContributorViolationAction',
            'sort_order' => 50,
            'enabled' => true,
        ],
        [
            'key' => 'contributor.manage_status_action',
            'type' => 'page_action',
            'surface' => 'contributor.show',
            'label' => 'Manage Contributor Status',
            'capabilities' => ['creator.remove'],
            'component' => 'ContributorStatusAction',
            'sort_order' => 60,
            'enabled' => true,
        ],
        [
            'key' => 'contributor.site_access_action',
            'type' => 'page_action',
            'surface' => 'contributor.show',
            'label' => 'Manage Site Access',
            'capabilities' => ['site.members'],
            'component' => 'ContributorSiteAccessAction',
            'sort_order' => 70,
            'enabled' => true,
        ],
        [
            'key' => 'contributor.role_action',
            'type' => 'page_action',
            'surface' => 'contributor.show',
            'label' => 'Manage Contributor Role',
            'capabilities' => ['creator.manage_roles'],
            'component' => 'ContributorRoleAction',
            'sort_order' => 80,
            'enabled' => true,
        ],
        [
            'key' => 'contributor.capabilities_manage_action',
            'type' => 'page_action',
            'surface' => 'contributor.show',
            'label' => 'Manage Contributor Capabilities',
            'capabilities' => ['contributor.capabilities.manage'],
            'component' => 'ContributorCapabilitiesManageAction',
            'sort_order' => 90,
            'enabled' => true,
        ],
        [
            'key' => 'invitations.create_action',
            'type' => 'page_action',
            'surface' => 'invitations.index',
            'label' => 'Create Invitation',
            'capabilities' => ['creator.invite'],
            'component' => 'InvitationCreateAction',
            'sort_order' => 10,
            'enabled' => true,
        ],
        [
            'key' => 'violations.resolve_action',
            'type' => 'page_action',
            'surface' => 'violations.index',
            'label' => 'Resolve Violation',
            'capabilities' => ['violation.resolve'],
            'component' => 'ViolationResolveAction',
            'sort_order' => 10,
            'enabled' => true,
        ],
        [
            'key' => 'articles.pending.approve_action',
            'type' => 'page_action',
            'surface' => 'articles.pending',
            'label' => 'Approve Article',
            'capabilities' => ['content.approve'],
            'component' => 'PendingArticleApproveAction',
            'sort_order' => 10,
            'enabled' => true,
        ],
        [
            'key' => 'articles.pending.reject_action',
            'type' => 'page_action',
            'surface' => 'articles.pending',
            'label' => 'Reject Article',
            'capabilities' => ['content.reject'],
            'component' => 'PendingArticleRejectAction',
            'sort_order' => 20,
            'enabled' => true,
        ],
        [
            'key' => 'articles.create_action',
            'type' => 'page_action',
            'surface' => 'articles.index',
            'label' => 'Create Article',
            'capabilities' => ['content.create'],
            'component' => 'ArticleCreateAction',
            'sort_order' => 10,
            'enabled' => true,
        ],
        [
            'key' => 'contracts.create_action',
            'type' => 'page_action',
            'surface' => 'contract.index',
            'label' => 'Create Contract Draft',
            'capabilities' => ['contract.create'],
            'component' => 'ContractCreateAction',
            'sort_order' => 10,
            'enabled' => true,
        ],
        [
            'key' => 'contracts.edit_action',
            'type' => 'page_action',
            'surface' => 'contract.index',
            'label' => 'Edit Contract Draft',
            'capabilities' => ['contract.edit'],
            'component' => 'ContractEditAction',
            'sort_order' => 20,
            'enabled' => true,
        ],
        [
            'key' => 'contracts.publish_action',
            'type' => 'page_action',
            'surface' => 'contract.index',
            'label' => 'Publish Contract',
            'capabilities' => ['contract.publish'],
            'component' => 'ContractPublishAction',
            'sort_order' => 30,
            'enabled' => true,
        ],
        [
            'key' => 'contracts.delete_action',
            'type' => 'page_action',
            'surface' => 'contract.index',
            'label' => 'Delete Contract Draft',
            'capabilities' => ['contract.archive'],
            'component' => 'ContractDeleteAction',
            'sort_order' => 40,
            'enabled' => true,
        ],
        [
            'key' => 'contracts.clone_action',
            'type' => 'page_action',
            'surface' => 'contract.index',
            'label' => 'Clone Contract',
            'capabilities' => ['contract.create'],
            'component' => 'ContractCloneAction',
            'sort_order' => 50,
            'enabled' => true,
        ],
        [
            'key' => 'guidelines.create_action',
            'type' => 'page_action',
            'surface' => 'guideline.index',
            'label' => 'Create Guidelines Draft',
            'capabilities' => ['guideline.create'],
            'component' => 'GuidelineCreateAction',
            'sort_order' => 10,
            'enabled' => true,
        ],
        [
            'key' => 'guidelines.edit_action',
            'type' => 'page_action',
            'surface' => 'guideline.index',
            'label' => 'Edit Guidelines Draft',
            'capabilities' => ['guideline.edit'],
            'component' => 'GuidelineEditAction',
            'sort_order' => 20,
            'enabled' => true,
        ],
        [
            'key' => 'guidelines.publish_action',
            'type' => 'page_action',
            'surface' => 'guideline.index',
            'label' => 'Publish Guidelines',
            'capabilities' => ['guideline.publish'],
            'component' => 'GuidelinePublishAction',
            'sort_order' => 30,
            'enabled' => true,
        ],
        [
            'key' => 'guidelines.delete_action',
            'type' => 'page_action',
            'surface' => 'guideline.index',
            'label' => 'Delete Guidelines Draft',
            'capabilities' => ['guideline.archive'],
            'component' => 'GuidelineDeleteAction',
            'sort_order' => 40,
            'enabled' => true,
        ],
        [
            'key' => 'guidelines.clone_action',
            'type' => 'page_action',
            'surface' => 'guideline.index',
            'label' => 'Clone Guidelines',
            'capabilities' => ['guideline.create'],
            'component' => 'GuidelineCloneAction',
            'sort_order' => 50,
            'enabled' => true,
        ],
    ],

];
