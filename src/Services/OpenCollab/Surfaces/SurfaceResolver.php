<?php

namespace App\Services\OpenCollab\Surfaces;

use App\Framework\Support\SiteContext;

/**
 * Resolves the configured sections for a named Open Collab surface.
 *
 * This intentionally works at section level rather than tiny widget/card level.
 * A stats grid is one section, a table/content area is one section, and the
 * page merely orchestrates the ordered section manifest.
 */
final class SurfaceResolver
{
    /**
     * @return SurfaceSection[]
     */
    public function resolve(string $surface, ?string $siteSlug = null): array
    {
        $siteSlug ??= SiteContext::slug();

        $definitions = $this->definitionsFor($surface, $siteSlug);

        $sections = array_map(
            static fn(array $definition): SurfaceSection => SurfaceSection::fromArray($definition),
            $definitions,
        );

        usort($sections, static function (SurfaceSection $a, SurfaceSection $b): int {
            $aOrder = (int) ($a->layout()['order'] ?? 0);
            $bOrder = (int) ($b->layout()['order'] ?? 0);

            return $aOrder <=> $bOrder ?: strcmp($a->key(), $b->key());
        });

        return $sections;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function manifest(string $surface, ?string $siteSlug = null): array
    {
        return array_map(
            static fn(SurfaceSection $section): array => $section->toManifest(),
            $this->resolve($surface, $siteSlug),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definitionsFor(string $surface, string $siteSlug): array
    {
        $configured = $this->configuredDefinitions($surface, $siteSlug);

        if ($configured !== []) {
            return $configured;
        }

        return $this->defaultDefinitions($siteSlug)[$surface] ?? [];
    }

    /**
     * Allows a future config/open_collab_surfaces.php file to override sections
     * globally or per site without changing page controllers.
     *
     * Supported shape:
     * [
     *   'surfaces' => ['payouts.index' => [...]],
     *   'sites' => ['guitar-world' => ['payouts.index' => [...]]],
     * ]
     *
     * @return array<int, array<string, mixed>>
     */
    private function configuredDefinitions(string $surface, string $siteSlug): array
    {
        if (!function_exists('config')) {
            return [];
        }

        $siteDefinitions = config("open_collab_surfaces.sites.{$siteSlug}.{$surface}");
        if (is_array($siteDefinitions) && $siteDefinitions !== []) {
            return $siteDefinitions;
        }

        $surfaceDefinitions = config("open_collab_surfaces.surfaces.{$surface}");
        return is_array($surfaceDefinitions) ? $surfaceDefinitions : [];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function defaultDefinitions(string $siteSlug): array
    {
        $dashboardEarnings = "/api/{$siteSlug}/open-collab/dashboard/widgets/earnings";

        return [
            'payouts.index' => [
                [
                    'key' => 'payouts.stats',
                    'title' => 'Payout stats',
                    'component' => 'payout_stats_grid',
                    'endpoint' => $dashboardEarnings,
                    'layout' => ['order' => 10, 'span' => 12],
                    'permissions' => ['payout.view'],
                ],
                [
                    'key' => 'payouts.history_table',
                    'title' => 'Payout history',
                    'component' => 'payout_history_table',
                    'endpoint' => "/api/{$siteSlug}/open-collab/payouts",
                    'layout' => ['order' => 20, 'span' => 12],
                    'permissions' => ['payout.view'],
                ],
            ],

            'earnings.index' => [
                [
                    'key' => 'earnings.stats',
                    'title' => 'Earnings stats',
                    'component' => 'earnings_stats_grid',
                    'endpoint' => $dashboardEarnings,
                    'layout' => ['order' => 10, 'span' => 12],
                    'permissions' => ['payout.view'],
                ],
                [
                    'key' => 'earnings.transactions_table',
                    'title' => 'Earnings table',
                    'component' => 'earnings_finance_table',
                    'endpoint' => $dashboardEarnings,
                    'layout' => ['order' => 20, 'span' => 12],
                    'permissions' => ['payout.view'],
                ],
            ],

            'disputes.index' => [
                [
                    'key' => 'disputes.stats',
                    'title' => 'Dispute stats',
                    'component' => 'dispute_stats_grid',
                    'endpoint' => "/api/{$siteSlug}/open-collab/disputes",
                    'layout' => ['order' => 10, 'span' => 12],
                    'permissions' => ['payout.view'],
                ],
                [
                    'key' => 'disputes.table',
                    'title' => 'Disputes table',
                    'component' => 'disputes_table',
                    'endpoint' => "/api/{$siteSlug}/open-collab/disputes",
                    'layout' => ['order' => 20, 'span' => 12],
                    'permissions' => ['payout.view'],
                ],
            ],

            'admin.disputes.index' => [
                [
                    'key' => 'admin.disputes.summary_stats',
                    'title' => 'Dispute summary',
                    'component' => 'admin_dispute_summary_stats',
                    'endpoint' => "/api/{$siteSlug}/open-collab/admin/disputes",
                    'layout' => ['order' => 5, 'span' => 12],
                    'permissions' => ['payout.view', 'payout.approve'],
                ],
                [
                    'key' => 'admin.disputes.stats',
                    'title' => 'Admin dispute stats',
                    'component' => 'admin_dispute_stats_grid',
                    'endpoint' => "/api/{$siteSlug}/open-collab/admin/disputes",
                    'layout' => ['order' => 10, 'span' => 12],
                    'permissions' => ['payout.view', 'payout.approve'],
                ],
            ],

            'admin.payouts.index' => [
                [
                    'key' => 'admin.payouts.summary_stats',
                    'title' => 'Payout summary',
                    'component' => 'admin_payout_summary_stats',
                    'endpoint' => "/api/{$siteSlug}/open-collab/admin/payouts/stats",
                    'layout' => ['order' => 5, 'span' => 12],
                    'permissions' => ['payout.view', 'payout.approve'],
                ],
                [
                    'key' => 'admin.payouts.stats',
                    'title' => 'Admin payout stats',
                    'component' => 'admin_payout_stats_grid',
                    'endpoint' => "/api/{$siteSlug}/open-collab/admin/payouts?per_page=200",
                    'layout' => ['order' => 10, 'span' => 12],
                    'permissions' => ['payout.view', 'payout.approve'],
                ],
            ],

            'admin.violations.index' => [
                [
                    'key' => 'admin.violations.summary_stats',
                    'title' => 'Violation summary',
                    'component' => 'admin_violation_stats_grid',
                    'endpoint' => "/api/{$siteSlug}/open-collab/admin/violations",
                    'layout' => ['order' => 5, 'span' => 12],
                    'permissions' => ['violations.view'],
                ],
                [
                    'key' => 'admin.violations.table',
                    'title' => 'Violations',
                    'component' => 'admin_violations_table',
                    'endpoint' => "/api/{$siteSlug}/open-collab/admin/violations",
                    'layout' => ['order' => 10, 'span' => 12],
                    'permissions' => ['violations.view'],
                ],
            ],
        ];
    }
}