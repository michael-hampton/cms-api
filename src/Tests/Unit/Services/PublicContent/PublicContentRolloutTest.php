<?php

namespace App\Tests\Unit\Services\PublicContent;

use App\Models\Page;
use App\Services\PublicContent\PublicContentRollout;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicContentRolloutTest extends TestCase
{
    public function testPreviewIsEnabledByDefault(): void
    {
        $rollout = new PublicContentRollout();

        self::assertTrue($rollout->previewEnabled());
    }

    public function testProductionRolloutIsDisabledByDefault(): void
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->site_id = 1;
        $page->page_type = 'article';

        $rollout = new PublicContentRollout();

        self::assertFalse($rollout->enabledFor($page));
    }
}
