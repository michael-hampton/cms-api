<?php

namespace App\Tests\Unit\ViewModels\OpenCollab;

use App\Models\TermsVersion;
use App\Services\OpenCollab\OpenCollabDocumentService;
use App\ViewModels\OpenCollab\OnboardingLegalDocumentViewModelFactory;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class OnboardingLegalDocumentTermsViewModelTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_for_terms_returns_null_when_terms_are_missing(): void
    {
        $factory = new OnboardingLegalDocumentViewModelFactory(
            Mockery::mock(OpenCollabDocumentService::class)
        );

        $this->assertNull($factory->forTerms(null));
    }

    public function test_for_terms_uses_rendered_snapshot_and_material_metadata(): void
    {
        $documentService = Mockery::mock(OpenCollabDocumentService::class);
        $documentService->shouldNotReceive('previewUrl');
        $documentService->shouldNotReceive('downloadUrl');

        $factory = new OnboardingLegalDocumentViewModelFactory($documentService);
        $reflection = new ReflectionClass(TermsVersion::class);
        $terms = $reflection->newInstanceWithoutConstructor();
        $terms->forceFill([
            'id' => 7,
            'title' => 'Contributor Terms',
            'semantic_version' => '1.2.0',
            'source_content' => '<p>Source content</p>',
            'source_format' => 'html',
            'rendered_content' => '<p>Rendered snapshot</p>',
            'rendered_format' => 'html',
            'rendered_hash' => str_repeat('a', 64),
            'is_material_change' => true,
            'change_summary' => 'Revenue share updated.',
            'document_id' => null,
            'source_document_id' => null,
        ]);

        $vm = $factory->forTerms($terms);

        $this->assertSame(7, $vm['id']);
        $this->assertSame('terms', $vm['type']);
        $this->assertSame('1.2.0', $vm['version']);
        $this->assertSame('html', $vm['mode']);
        $this->assertSame('<p>Rendered snapshot</p>', $vm['content']);
        $this->assertTrue($vm['isMaterialChange']);
        $this->assertSame('Revenue share updated.', $vm['changeSummary']);
        $this->assertSame(str_repeat('a', 64), $vm['renderedHash']);
    }
}
