<?php

namespace App\Tests\Unit\ViewModels\OpenCollab;

use App\Framework\Support\SiteContext;
use App\Models\Contract;
use App\Models\Guideline;
use App\Models\OpenCollabDocument;
use App\Services\OpenCollab\OpenCollabDocumentService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\ViewModels\OpenCollab\OnboardingLegalDocumentViewModelFactory;
use Mockery;
use Mockery\MockInterface;

class OnboardingLegalDocumentViewModelFactoryTest extends FunctionalTestCase
{
    private OpenCollabDocumentService&MockInterface $documentService;
    private OnboardingLegalDocumentViewModelFactory $factory;

    public function test_for_contract_returns_null_when_contract_is_null(): void
    {
        $this->assertNull($this->factory->forContract(null));
    }

    public function test_for_guideline_returns_null_when_guideline_is_null(): void
    {
        $this->assertNull($this->factory->forGuideline(null));
    }

    public function test_contract_with_inline_content_uses_html_mode(): void
    {
        $contract = $this->contract([
            'id' => 10,
            'title' => 'Custom Contract',
            'version' => 3,
            'content' => '<p>Contract terms</p>',
            'content_format' => 'html',
            'document_id' => null,
            'source_document_id' => null,
        ]);

        $vm = $this->factory->forContract($contract);

        $this->assertSame(10, $vm['id']);
        $this->assertSame('contract', $vm['type']);
        $this->assertSame('Custom Contract', $vm['title']);
        $this->assertSame(3, $vm['version']);
        $this->assertSame('html', $vm['mode']);
        $this->assertSame('<p>Contract terms</p>', $vm['content']);
        $this->assertNull($vm['documentUrl']);
        $this->assertNull($vm['downloadUrl']);
        $this->assertNull($vm['filename']);
        $this->assertNull($vm['mimeType']);
        $this->assertFalse($vm['accepted']);
    }

    public function test_guideline_with_inline_content_uses_html_mode(): void
    {
        $guideline = $this->guideline([
            'id' => 20,
            'title' => 'Editorial Rules',
            'version' => 4,
            'content' => '<p>Guideline content</p>',
            'content_format' => 'html',
            'document_id' => null,
            'source_document_id' => null,
        ]);

        $vm = $this->factory->forGuideline($guideline);

        $this->assertSame(20, $vm['id']);
        $this->assertSame('guideline', $vm['type']);
        $this->assertSame('Editorial Rules', $vm['title']);
        $this->assertSame(4, $vm['version']);
        $this->assertSame('html', $vm['mode']);
        $this->assertSame('<p>Guideline content</p>', $vm['content']);
        $this->assertNull($vm['documentUrl']);
        $this->assertNull($vm['downloadUrl']);
        $this->assertNull($vm['filename']);
        $this->assertNull($vm['mimeType']);
        $this->assertFalse($vm['accepted']);
    }

    public function test_contract_with_text_content_uses_html_mode(): void
    {
        $contract = $this->contract([
            'id' => 11,
            'title' => 'Text Contract',
            'version' => 1,
            'content' => 'Plain text contract',
            'content_format' => 'text',
        ]);

        $vm = $this->factory->forContract($contract);

        $this->assertSame('html', $vm['mode']);
        $this->assertSame('Plain text contract', $vm['content']);
    }

    public function test_guideline_with_text_content_uses_html_mode(): void
    {
        $guideline = $this->guideline([
            'id' => 21,
            'title' => 'Text Guidelines',
            'version' => 1,
            'content' => 'Plain text guidelines',
            'content_format' => 'text',
        ]);

        $vm = $this->factory->forGuideline($guideline);

        $this->assertSame('html', $vm['mode']);
        $this->assertSame('Plain text guidelines', $vm['content']);
    }

    public function test_contract_without_content_but_with_document_uses_document_mode(): void
    {
        $document = $this->createDocument([
            'original_filename' => 'contract.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $this->documentService
            ->shouldReceive('previewUrl')
            ->once()
            ->with(Mockery::on(fn ($arg) => (int)$arg->id === (int)$document->id))
            ->andReturn('/preview/contract.pdf');

        $this->documentService
            ->shouldReceive('downloadUrl')
            ->once()
            ->with(Mockery::on(fn ($arg) => (int)$arg->id === (int)$document->id))
            ->andReturn('/download/contract.pdf');

        $contract = $this->contract([
            'id' => 12,
            'title' => 'PDF Contract',
            'version' => 2,
            'content' => '',
            'content_format' => 'pdf',
            'document_id' => $document->id,
            'source_document_id' => null,
        ]);

        $vm = $this->factory->forContract($contract);

        $this->assertSame('document', $vm['mode']);
        $this->assertNull($vm['content']);
        $this->assertSame('/preview/contract.pdf', $vm['documentUrl']);
        $this->assertSame('/download/contract.pdf', $vm['downloadUrl']);
        $this->assertSame('contract.pdf', $vm['filename']);
        $this->assertSame('application/pdf', $vm['mimeType']);
    }

    public function test_guideline_without_content_but_with_document_uses_document_mode(): void
    {
        $document = $this->createDocument([
            'original_filename' => 'guidelines.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $this->documentService
            ->shouldReceive('previewUrl')
            ->once()
            ->andReturn('/preview/guidelines.pdf');

        $this->documentService
            ->shouldReceive('downloadUrl')
            ->once()
            ->andReturn('/download/guidelines.pdf');

        $guideline = $this->guideline([
            'id' => 22,
            'title' => 'PDF Guidelines',
            'version' => 5,
            'content' => '',
            'content_format' => 'pdf',
            'document_id' => $document->id,
            'source_document_id' => null,
        ]);

        $vm = $this->factory->forGuideline($guideline);

        $this->assertSame('document', $vm['mode']);
        $this->assertNull($vm['content']);
        $this->assertSame('/preview/guidelines.pdf', $vm['documentUrl']);
        $this->assertSame('/download/guidelines.pdf', $vm['downloadUrl']);
        $this->assertSame('guidelines.pdf', $vm['filename']);
        $this->assertSame('application/pdf', $vm['mimeType']);
    }

//    public function test_inline_content_takes_precedence_over_document(): void
//    {
//        $document = $this->createDocument([
//            'original_filename' => 'contract.pdf',
//            'mime_type' => 'application/pdf',
//        ]);
//
//        $this->documentService->shouldNotReceive('previewUrl');
//        $this->documentService->shouldNotReceive('downloadUrl');
//
//        $contract = $this->contract([
//            'id' => 13,
//            'title' => 'Contract',
//            'version' => 1,
//            'content' => '<p>Inline contract</p>',
//            'content_format' => 'html',
//            'document_id' => $document->id,
//        ]);
//
//        $vm = $this->factory->forContract($contract);
//
//        $this->assertSame('html', $vm['mode']);
//        $this->assertSame('<p>Inline contract</p>', $vm['content']);
//        $this->assertNull($vm['documentUrl']);
//        $this->assertNull($vm['downloadUrl']);
//    }

    public function test_contract_falls_back_to_source_document_id(): void
    {
        $document = $this->createDocument([
            'original_filename' => 'source-contract.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $this->documentService
            ->shouldReceive('previewUrl')
            ->once()
            ->andReturn('/preview/source-contract.pdf');

        $this->documentService
            ->shouldReceive('downloadUrl')
            ->once()
            ->andReturn('/download/source-contract.pdf');

        $contract = $this->contract([
            'content' => null,
            'content_format' => 'pdf',
            'document_id' => null,
            'source_document_id' => $document->id,
        ]);

        $vm = $this->factory->forContract($contract);

        $this->assertSame('document', $vm['mode']);
        $this->assertSame('source-contract.pdf', $vm['filename']);
        $this->assertSame('/preview/source-contract.pdf', $vm['documentUrl']);
        $this->assertSame('/download/source-contract.pdf', $vm['downloadUrl']);
    }

    public function test_guideline_falls_back_to_source_document_id(): void
    {
        $document = $this->createDocument([
            'original_filename' => 'source-guidelines.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $this->documentService
            ->shouldReceive('previewUrl')
            ->once()
            ->andReturn('/preview/source-guidelines.pdf');

        $this->documentService
            ->shouldReceive('downloadUrl')
            ->once()
            ->andReturn('/download/source-guidelines.pdf');

        $guideline = $this->guideline([
            'content' => null,
            'content_format' => 'pdf',
            'document_id' => null,
            'source_document_id' => $document->id,
        ]);

        $vm = $this->factory->forGuideline($guideline);

        $this->assertSame('document', $vm['mode']);
        $this->assertSame('source-guidelines.pdf', $vm['filename']);
        $this->assertSame('/preview/source-guidelines.pdf', $vm['documentUrl']);
        $this->assertSame('/download/source-guidelines.pdf', $vm['downloadUrl']);
    }

    public function test_document_id_takes_precedence_over_source_document_id(): void
    {
        $sourceDocument = $this->createDocument([
            'original_filename' => 'source.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $document = $this->createDocument([
            'original_filename' => 'actual.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $this->documentService
            ->shouldReceive('previewUrl')
            ->once()
            ->with(Mockery::on(fn ($arg) => (int)$arg->id === (int)$document->id))
            ->andReturn('/preview/actual.pdf');

        $this->documentService
            ->shouldReceive('downloadUrl')
            ->once()
            ->with(Mockery::on(fn ($arg) => (int)$arg->id === (int)$document->id))
            ->andReturn('/download/actual.pdf');

        $contract = $this->contract([
            'content' => null,
            'content_format' => 'pdf',
            'document_id' => $document->id,
            'source_document_id' => $sourceDocument->id,
        ]);

        $vm = $this->factory->forContract($contract);

        $this->assertSame('actual.pdf', $vm['filename']);
        $this->assertSame('/preview/actual.pdf', $vm['documentUrl']);
        $this->assertSame('/download/actual.pdf', $vm['downloadUrl']);
    }

    public function test_missing_document_still_returns_document_mode_with_null_urls(): void
    {
        $contract = $this->contract([
            'id' => 14,
            'title' => 'Missing Document Contract',
            'version' => 1,
            'content' => null,
            'content_format' => 'pdf',
            'document_id' => 999999,
            'source_document_id' => null,
        ]);

        $vm = $this->factory->forContract($contract);

        $this->assertSame('document', $vm['mode']);
        $this->assertNull($vm['content']);
        $this->assertNull($vm['documentUrl']);
        $this->assertNull($vm['downloadUrl']);
        $this->assertNull($vm['filename']);
        $this->assertNull($vm['mimeType']);
    }

    public function test_contract_title_falls_back_to_contributor_agreement(): void
    {
        $contract = $this->contract([
            'title' => null,
            'content' => '<p>Terms</p>',
            'content_format' => 'html',
        ]);

        $vm = $this->factory->forContract($contract);

        $this->assertSame('Contributor Agreement', $vm['title']);
    }

    public function test_guideline_title_falls_back_to_brand_editorial_guidelines(): void
    {
        $guideline = $this->guideline([
            'title' => null,
            'content' => '<p>Rules</p>',
            'content_format' => 'html',
        ]);

        $vm = $this->factory->forGuideline($guideline);

        $this->assertSame('Brand & Editorial Guidelines', $vm['title']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->documentService = Mockery::mock(OpenCollabDocumentService::class);
        $this->factory = new OnboardingLegalDocumentViewModelFactory($this->documentService);
    }

    private function contract(array $attributes = []): Contract
    {
        $contract = new Contract();

        foreach (array_merge([
            'id' => 1,
            'title' => 'Contributor Agreement',
            'version' => 1,
            'content' => '<p>Terms</p>',
            'content_format' => 'html',
            'document_id' => null,
            'source_document_id' => null,
        ], $attributes) as $key => $value) {
            $contract->{$key} = $value;
        }

        return $contract;
    }

    private function guideline(array $attributes = []): Guideline
    {
        $guideline = new Guideline();

        foreach (array_merge([
            'id' => 2,
            'title' => 'Brand Guidelines',
            'version' => 1,
            'content' => '<p>Guidelines</p>',
            'content_format' => 'html',
            'document_id' => null,
            'source_document_id' => null,
        ], $attributes) as $key => $value) {
            $guideline->{$key} = $value;
        }

        return $guideline;
    }

    private function createDocument(array $attributes = []): OpenCollabDocument
    {
        return OpenCollabDocument::create(array_merge([
            'site_id' => $this->siteId ?? 1,
            'category' => 'general_open_collab_document',
            'original_filename' => 'document.pdf',
            'stored_filename' => 'document.pdf',
            'disk' => 'local',
            'path' => 'open-collab/sites/1/documents/1/document.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 100,
            'metadata_json' => [],
        ], $attributes));
    }
}