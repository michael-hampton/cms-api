<?php

namespace App\Tests\Unit\Listeners\PublicContent;

use App\Events\PublicContent\PublicContentDefaultLocaleApplied;
use App\Framework\Support\Logger;
use App\Listeners\PublicContent\LogPublicContentDefaultLocaleApplied;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

final class LogPublicContentDefaultLocaleAppliedTest extends TestCase
{
    private MockInterface $logger;

    protected function setUp(): void
    {
        $this->logger = Mockery::mock(Logger::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_logs_the_default_locale_application(): void
    {
        $this->logger->shouldReceive('info')
            ->once()
            ->with(
                'Public content default locale applied',
                [
                    'site_id' => 4,
                    'page_id' => 123,
                    'default_language' => 'en',
                ],
            );

        $listener = new LogPublicContentDefaultLocaleApplied($this->logger);

        $listener->handle(new PublicContentDefaultLocaleApplied(
            siteId: 4,
            pageId: 123,
            defaultLanguage: 'en',
        ));

        $this->addToAssertionCount(1);
    }
}
