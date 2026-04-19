<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Jobs\BuildMemberProfileJob;
use App\Models\Member;
use App\Models\Site;

/**
 * Ticket 8 — Profile Enrichment Console Command.
 *
 * Dispatches BuildMemberProfileJob for every member on every site.
 * Pattern mirrors BuildMemberStats / BuildMemberSegments.
 *
 * Usage:
 *   php artisan members:build-profiles
 *   php artisan members:build-profiles {site_id}
 *   php artisan members:build-profiles --member_id=42
 */
class BuildMemberProfiles extends Command
{
    use ReportsCommandResult;

    const SUCCESS = 1;
    const FAILURE = 0;

    public $description = 'Dispatches profile enrichment jobs for all members across all sites.';
    protected $signature = 'members:build-profiles {site_id?} {--member_id=}';

    public function handle(): int
    {
        $result = $this->createResult('members:build-profiles');

        $sites = $this->argument('site_id')
            ? Site::where('id', $this->argument('site_id'))->get()
            : Site::all();

        foreach ($sites as $site) {
            $query = Member::query();

            if ($memberId = $this->option('member_id')) {
                $query->where('id', (int)$memberId);
            }

            $query->chunkById(100, function ($members) use ($site, $result) {
                foreach ($members as $member) {
                    try {
                        dispatch(BuildMemberProfileJob::for($member->id, $site->id));
                        $result->incrementSucceeded();
                    } catch (\Throwable $e) {
                        $this->reportFailure(
                            result: $result,
                            message: "Failed to dispatch for member {$member->id}, site {$site->id}: {$e->getMessage()}",
                            context: ['member_id' => $member->id, 'site_id' => $site->id],
                            throwable: $e,
                        );
                    }
                }
            });
        }

        $this->reportResult($result);

        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}