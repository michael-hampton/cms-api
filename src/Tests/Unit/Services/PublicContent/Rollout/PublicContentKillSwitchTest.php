<?php

namespace App\Tests\Unit\Services\PublicContent\Rollout;

use App\Models\Page;
use App\Services\PublicContent\PublicContentRollout;
use App\Services\PublicContent\Rollout\PublicContentKillSwitch;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicContentKillSwitchTest extends TestCase
{
    private string $statePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statePath = tempnam(sys_get_temp_dir(), 'pc-kill');
        @unlink($this->statePath);
        putenv('PUBLIC_CONTENT_V2_ENABLED=true');
        putenv('PUBLIC_CONTENT_V2_SITE_IDS=');
        putenv('PUBLIC_CONTENT_V2_EXCLUDED_SITE_IDS=');
        $_ENV['PUBLIC_CONTENT_V2_ENABLED'] = 'true';
        $_ENV['PUBLIC_CONTENT_V2_SITE_IDS'] = '';
        $_ENV['PUBLIC_CONTENT_V2_EXCLUDED_SITE_IDS'] = '';
    }

    protected function tearDown(): void
    {
        @unlink($this->statePath);
        putenv('PUBLIC_CONTENT_V2_ENABLED');
        putenv('PUBLIC_CONTENT_V2_SITE_IDS');
        putenv('PUBLIC_CONTENT_V2_EXCLUDED_SITE_IDS');
        unset($_ENV['PUBLIC_CONTENT_V2_ENABLED'], $_ENV['PUBLIC_CONTENT_V2_SITE_IDS'], $_ENV['PUBLIC_CONTENT_V2_EXCLUDED_SITE_IDS']);
        Mockery::close();
        parent::tearDown();
    }

    public function test_excluding_brand_forces_legacy_regardless_of_rollout_bucket(): void
    {
        $kill = new PublicContentKillSwitch($this->statePath, 60);
        $rollout = new PublicContentRollout($kill);

        $page = Mockery::mock(Page::class)->makePartial();
        $page->site_id = 42;
        $page->page_type = 'article';

        self::assertTrue($rollout->enabledFor($page));

        $kill->excludeSite(42, 'test');

        self::assertTrue($kill->isSiteExcluded(42));
        self::assertFalse($rollout->enabledFor($page));
    }

    public function test_rollback_rehearsal_reports_cache_clear_bound(): void
    {
        $kill = new PublicContentKillSwitch($this->statePath, 45);
        $result = $kill->rehearseRollback(7);

        self::assertTrue($result['excluded']);
        self::assertTrue($result['legacy_forced']);
        self::assertSame(45, $result['cache_clear_seconds']);
        self::assertNotNull($result['cache_clear_deadline']);
    }

    public function test_env_excluded_sites_also_force_legacy(): void
    {
        putenv('PUBLIC_CONTENT_V2_EXCLUDED_SITE_IDS=9,10');
        $_ENV['PUBLIC_CONTENT_V2_EXCLUDED_SITE_IDS'] = '9,10';

        $kill = new PublicContentKillSwitch($this->statePath, 60);
        $rollout = new PublicContentRollout($kill);

        $page = Mockery::mock(Page::class)->makePartial();
        $page->site_id = 9;
        $page->page_type = 'article';

        self::assertFalse($rollout->enabledFor($page));
    }
}
