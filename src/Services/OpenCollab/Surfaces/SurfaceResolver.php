<?php

namespace App\Services\OpenCollab\Surfaces;

use App\Framework\Support\SiteContext;

/**
 * Resolves the configured sections for a named Open Collab surface.
 *
 * This intentionally works at section level rather than tiny widget/card level.
 * A stats grid is one section, a table is one section, and the page merely
 * orchestrates the ordered section list.
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
    private function definitionsFor(string $surface, string $siteSlug): array
    {
        $configured = $this->configuredDefinitions($surface, $siteSlug);

        if ($configured !== []) {
            return $configured;
        }

        return $this->defaultDefinitions()[$surface] ?? [];
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
    private function defaultDefinitions(): array
    {
        return [
            'payouts.index' => [
                [
                    'key' => 'payouts.stats',
                    'title' => 'Payout stats',
                    'view' => 'open-collab.sections.payouts.stats',
                    'layout' => ['order' => 10, 'span' => 12],
                    'permissions' => ['payout.view'],
                ],
                [
                    'key' => 'payouts.history_table',
                    'title' => 'Payout history',
                    'view' => 'open-collab.sections.payouts.history-table',
                    'layout' => ['order' => 20, 'span' => 12],
                    'permissions' => ['payout.view'],
                ],
            ],

            'earnings.index' => [
                [
                    'key' => 'earnings.stats',
                    'title' => 'Earnings stats',
                    'view' => 'open-collab.sections.earnings.stats',
                    'layout' => ['order' => 10, 'span' => 12],
                    'permissions' => ['payout.view'],
                ],
                [
                    'key' => 'earnings.transactions_table',
                    'title' => 'Earnings transactions',
                    'view' => 'open-collab.sections.earnings.transactions-table',
                    'layout' => ['order' => 20, 'span' => 12],
                    'permissions' => ['payout.view'],
                ],
            ],

            'disputes.index' => [
                [
                    'key' => 'disputes.stats',
                    'title' => 'Dispute stats',
                    'view' => 'open-collab.sections.disputes.stats',
                    'layout' => ['order' => 10, 'span' => 12],
                    'permissions' => ['payout.view'],
                ],
                [
                    'key' => 'disputes.table',
                    'title' => 'Disputes table',
                    'view' => 'open-collab.sections.disputes.table',
                    'layout' => ['order' => 20, 'span' => 12],
                    'permissions' => ['payout.view'],
                ],
            ],
        ];
    }
}
