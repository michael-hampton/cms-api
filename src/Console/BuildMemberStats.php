<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Models\Member;
use App\Models\Site;
use App\Services\MemberInsights\MemberStatEngine;

class BuildMemberStats extends Command
{
    use ReportsCommandResult;

    const SUCCESS = 1;
    const FAILURE = 0;
    public $description = 'Rebuilds aggregated engagement stats for members from source tables.';
    protected $signature = 'members:build-stats {site_id} {--member_id=}';

    public function __construct(
        private readonly MemberStatEngine $engine,
    )
    {
    }

    public function handle(): int
    {
        $result = $this->createResult('members:build-stats');
        $sites = Site::all();

        foreach ($sites as $site) {

            Member::query()
                ->chunkById(100, function ($members) use ($site, $result) {

                    foreach ($members as $member) {

                        try {
                            $this->engine->rebuild($member->id, $site->id);
                            $result->incrementSucceeded();
                        } catch (\Throwable $e) {
                            $this->reportFailure(
                                result: $result,
                                message: "Failed for member {$member->id}, site {$site->id}: {$e->getMessage()}",
                                context: [
                                    'member_id' => $member->id,
                                    'site_id' => $site->id,
                                ],
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