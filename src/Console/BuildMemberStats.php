<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Models\Member;
use App\Services\Members\MemberStatEngine;

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
        $siteId = (int)$this->argument('site_id');

        Member::where('site_id', $siteId)
            ->when(
                $this->option('member_id'),
                fn($q) => $q->where('id', (int)$this->option('member_id'))
            )
            ->chunkById(100, function ($members) use ($result, $siteId) {
                foreach ($members as $member) {
                    try {
                        $this->engine->rebuild($member->id, $siteId);
                        $result->incrementSucceeded();
                    } catch (\Throwable $e) {
                        $this->reportFailure(
                            result: $result,
                            message: "Failed to build stats for member {$member->id}: {$e->getMessage()}",
                            context: ['member_id' => $member->id, 'site_id' => $siteId],
                            throwable: $e
                        );
                    }
                }
            });

        $this->reportResult($result);

        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}