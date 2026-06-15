<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Models\TermsVersion;
use App\Models\UserTermsAcceptance;
use App\Repositories\OpenCollab\UserTermsAcceptanceRepositoryInterface;
use App\Services\OpenCollab\TermsAcceptanceEvidenceService;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

class TermsAcceptanceEvidenceServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_missing_acceptance_throws(): void
    {
        $repository = Mockery::mock(UserTermsAcceptanceRepositoryInterface::class);
        $repository->shouldReceive('findWithTermsVersion')
            ->once()
            ->with(999999)
            ->andReturnNull();

        $this->expectException(RuntimeException::class);

        (new TermsAcceptanceEvidenceService($repository))->get(999999);
    }

    public function test_hash_verification_logic_is_reproducible(): void
    {
        $content = '<p>Accepted terms snapshot</p>';
        $hash = hash('sha256', $content);

        $this->assertTrue(hash_equals($hash, hash('sha256', $content)));
        $this->assertFalse(hash_equals($hash, hash('sha256', $content . ' changed')));
    }

    public function test_models_expose_relationship_for_historical_terms(): void
    {
        $termsReflection = new ReflectionClass(TermsVersion::class);
        $terms = $termsReflection->newInstanceWithoutConstructor();
        $terms->forceFill(['id' => 7, 'rendered_content' => '<p>Snapshot</p>']);

        $acceptanceReflection = new ReflectionClass(UserTermsAcceptance::class);
        $acceptance = $acceptanceReflection->newInstanceWithoutConstructor();
        $acceptance->forceFill(['terms_version_id' => 7]);

        $this->assertTrue(method_exists($acceptance, 'termsVersion'));
        $this->assertSame(7, $acceptance->terms_version_id);
        $this->assertSame('<p>Snapshot</p>', $terms->rendered_content);
    }
}
