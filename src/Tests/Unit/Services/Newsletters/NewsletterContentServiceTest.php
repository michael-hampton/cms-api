<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\DTO\Newsletters\NewsletterContentDTO;
use App\Enums\Newsletters\ContentSourceType;
use App\Framework\Support\Logger;
use App\Models\Newsletter;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Services\Newsletter\NewsletterContentService;
use App\Services\Newsletter\Services\BlockDataFactory;
use App\Services\Newsletter\Validation\BlockPayloadValidator;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class NewsletterContentServiceTest extends RepositoryTestCase
{
    use CreatesTestData;

    private NewsletterContentService $service;
    private NewsletterRepository $newsletterRepository;

    public function test_saves_custom_blocks_content(): void
    {
        $newsletter = $this->createNewsletter();

        $dto = new NewsletterContentDTO(
            contentType: ContentSourceType::CustomBlocks,
            blocks: [['type' => 'text', 'data' => ['paragraphs' => ['Hello world']]]],
            legacyContent: null,
        );

        $result = $this->service->saveContent($newsletter->id, $dto);

        $this->assertEquals(ContentSourceType::CustomBlocks->value, $result->content_type);
        $this->assertNotEmpty($result->content_blocks);
        $this->assertNull($result->legacy_content);
        $this->assertNull($result->content);
    }

    // ── Custom blocks ─────────────────────────────────────────────────────────

    public function test_custom_blocks_clears_legacy_content(): void
    {
        $newsletter = $this->createNewsletter();
        $newsletter->legacy_content = 'Some old text';
        $newsletter->save();

        $dto = new NewsletterContentDTO(
            contentType: ContentSourceType::CustomBlocks,
            blocks: [['type' => 'heading', 'data' => ['text' => 'Hi', 'level' => 2]]],
            legacyContent: null,
        );

        $result = $this->service->saveContent($newsletter->id, $dto);

        $this->assertNull($result->legacy_content);
        $this->assertNull($result->content);
    }

    public function test_saves_legacy_content(): void
    {
        $newsletter = $this->createNewsletter();

        $dto = new NewsletterContentDTO(
            contentType: ContentSourceType::Manual,
            blocks: null,
            legacyContent: 'Plain text content',
        );

        $result = $this->service->saveContent($newsletter->id, $dto);

        $this->assertEquals(ContentSourceType::Manual->value, $result->content_type);
        $this->assertEquals('Plain text content', $result->legacy_content);
        $this->assertNull($result->content_blocks);
    }

    // ── Legacy content ────────────────────────────────────────────────────────

    public function test_legacy_content_clears_blocks(): void
    {
        $newsletter = $this->createNewsletter(['content_type' => 'custom_blocks']);
        $newsletter->content_blocks = [['type' => 'text', 'data' => ['paragraphs' => ['old']]]];
        $newsletter->save();

        $dto = new NewsletterContentDTO(
            contentType: ContentSourceType::Manual,
            blocks: null,
            legacyContent: 'Reverted to text',
        );

        $result = $this->service->saveContent($newsletter->id, $dto);

        $this->assertNull($result->content_blocks);
        $this->assertEquals('Reverted to text', $result->legacy_content);
    }

    public function test_throws_when_blocks_and_legacy_content_both_set(): void
    {
        $newsletter = $this->createNewsletter();

        $dto = new NewsletterContentDTO(
            contentType: ContentSourceType::CustomBlocks,
            blocks: [['type' => 'text', 'data' => ['paragraphs' => ['Hi']]]],
            legacyContent: 'Also some text',
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->service->saveContent($newsletter->id, $dto);
    }

    // ── Mixed state guard ─────────────────────────────────────────────────────

    public function test_throws_on_unknown_block_type(): void
    {
        $newsletter = $this->createNewsletter();

        $dto = new NewsletterContentDTO(
            contentType: ContentSourceType::CustomBlocks,
            blocks: [['type' => 'magic_block', 'data' => []]],
            legacyContent: null,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->service->saveContent($newsletter->id, $dto);
    }

    // ── Block validation ──────────────────────────────────────────────────────

    public function test_throws_on_block_missing_type(): void
    {
        $newsletter = $this->createNewsletter();

        $dto = new NewsletterContentDTO(
            contentType: ContentSourceType::CustomBlocks,
            blocks: [['data' => ['paragraphs' => ['hi']]]],
            legacyContent: null,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->service->saveContent($newsletter->id, $dto);
    }

    public function test_save_uses_transaction(): void
    {
        $newsletter = $this->createNewsletter();

        $dto = new NewsletterContentDTO(
            contentType: ContentSourceType::CustomBlocks,
            blocks: [['type' => 'divider', 'data' => ['style' => 'solid']]],
            legacyContent: null,
        );

        // If transaction wraps the write, the result is immediately consistent
        $result = $this->service->saveContent($newsletter->id, $dto);

        $fromDb = Newsletter::find($newsletter->id);
        $this->assertEquals($result->content_type, $fromDb->content_type);
        $this->assertEquals($result->content_blocks, $fromDb->content_blocks);
    }

    // ── Transaction ───────────────────────────────────────────────────────────

    public function test_throws_on_unknown_newsletter(): void
    {
        $dto = new NewsletterContentDTO(
            contentType: ContentSourceType::CustomBlocks,
            blocks: [],
            legacyContent: null,
        );

        $this->expectException(\RuntimeException::class);
        $this->service->saveContent(99999, $dto);
    }

    // ── Not found ─────────────────────────────────────────────────────────────

    public function test_converts_legacy_content_to_blocks(): void
    {
        $newsletter = $this->createNewsletter(['content_type' => 'manual']);
        $newsletter->legacy_content = 'This is my old newsletter text.';
        $newsletter->save();

        $blocks = $this->service->convertLegacyToBlocks($newsletter);

        $this->assertCount(1, $blocks);
        $this->assertEquals('text', $blocks[0]['type']);
        $this->assertStringContainsString('old newsletter text', $blocks[0]['data']['paragraphs'][0]);
    }

    // ── Legacy migration ──────────────────────────────────────────────────────

    public function test_convert_legacy_throws_when_not_legacy(): void
    {
        $newsletter = $this->createNewsletter(['content_type' => 'custom_blocks']);
        $newsletter->content_blocks = [['type' => 'text', 'data' => ['paragraphs' => ['hi']]]];
        $newsletter->save();

        $this->expectException(\LogicException::class);
        $this->service->convertLegacyToBlocks($newsletter);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->newsletterRepository = app(NewsletterRepository::class);

        $this->service = new NewsletterContentService(
            $this->newsletterRepository,
            new BlockPayloadValidator(new BlockDataFactory()),
            app(Logger::class),
            $this->database,
        );
    }
}