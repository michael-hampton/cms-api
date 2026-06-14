<?php

namespace App\Tests\Unit\Actions\OpenCollab\Legal;

use App\Actions\OpenCollab\Legal\PublishTermsVersionAction;
use App\Models\TermsVersion;
use App\Services\OpenCollab\TermsVersionService;
use Mockery;
use PHPUnit\Framework\TestCase;

class PublishTermsVersionActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_execute_delegates_to_service(): void
    {
        $service = Mockery::mock(TermsVersionService::class);
        $terms = Mockery::mock(TermsVersion::class);
        $published = Mockery::mock(TermsVersion::class);

        $service->shouldReceive('publish')->once()->with($terms, 99)->andReturn($published);

        $this->assertSame($published, (new PublishTermsVersionAction($service))->execute($terms, 99));
    }
}
