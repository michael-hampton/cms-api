<?php

namespace App\Tests\Unit\Actions\OpenCollab\Legal;

use App\Actions\OpenCollab\Legal\AcceptTermsVersionAction;
use App\Models\TermsVersion;
use App\Models\UserTermsAcceptance;
use App\Services\OpenCollab\TermsVersionService;
use Mockery;
use PHPUnit\Framework\TestCase;

class AcceptTermsVersionActionTest extends TestCase
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
        $acceptance = Mockery::mock(UserTermsAcceptance::class);

        $service->shouldReceive('accept')
            ->once()
            ->with($terms, 20, '127.0.0.1', 'PHPUnit', 'onboarding')
            ->andReturn($acceptance);

        $result = (new AcceptTermsVersionAction($service))->execute(
            $terms,
            20,
            '127.0.0.1',
            'PHPUnit',
        );

        $this->assertSame($acceptance, $result);
    }
}
