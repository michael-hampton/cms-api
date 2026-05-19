<?php

namespace App\Services\OpenCollab\Dashboard\Contracts;

use App\Models\User;

/**
 * Every dashboard widget must implement this contract.
 *
 * Widgets are responsible for:
 *   - self-identifying (key, title)
 *   - controlling their own visibility
 *   - returning their own data given an authenticated user
 *
 * Widgets must NOT:
 *   - format data for HTML presentation
 *   - access sessions, globals, or facades
 *   - call other widgets
 */
interface DashboardWidgetInterface
{
    /**
     * Unique machine-readable identifier.
     * Used as the route segment and the config/DB key.
     * Example: 'earnings', 'drafts', 'activity', 'onboarding'
     */
    public function key(): string;

    /**
     * Human-readable display title for the widget.
     */
    public function title(): string;

    /**
     * Whether this widget should be available to the given user.
     * Visibility is evaluated BEFORE data() is called.
     */
    public function visibleFor(User $user): bool;

    /**
     * Return the widget's data payload.
     * All amounts should be in pence/cents (integers).
     * All formatting is the responsibility of the presentation layer.
     */
    public function data(User $user): array;
}