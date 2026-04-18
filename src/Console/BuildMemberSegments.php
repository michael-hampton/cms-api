<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Jobs\ProcessMemberSegmentationJob;
use App\Models\Member;
use App\Models\Site;

/**
 * Dispatches ProcessMemberSegmentationJob for every member on every site.
 *
 * Mirrors the pattern established in BuildMemberStats:
 *   - chunks members to avoid memory exhaustion
 *   - reports per-member failures without aborting the full run
 *   - exits 0 on clean run, 1 if any member failed
 *
 * Usage:
 *   php artisan members:build-segments
 *   php artisan members:build-segments {site_id}          # single site
 *   php artisan members:build-segments --member_id=42     # single member
 */
class BuildMemberSegments extends Command
{
    use ReportsCommandResult;

    const SUCCESS = 1;
    const FAILURE = 0;

    public $description = 'Dispatches segmentation jobs for all members across all sites.';
    protected $signature = 'members:build-segments {site_id?} {--member_id=}';

    public function handle(): int
    {
        $result = $this->createResult('members:build-segments');

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
                        dispatch(ProcessMemberSegmentationJob::for($member->id, $site->id));
                        $result->incrementSucceeded();
                    } catch (\Throwable $e) {
                        $this->reportFailure(
                            result: $result,
                            message: "Failed to dispatch for member {$member->id}, site {$site->id}: {$e->getMessage()}",
                            context: ['member_id' => $member->id, 'site_id' => $site->id],
                            throwable: $e
                        );
                    }
                }
            });
        }

        $this->reportResult($result);

        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}