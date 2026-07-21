<?php

namespace App\Tests\Unit\Services\PublicContent\Config;

use App\Models\ConfigDocument;
use App\Repositories\PublicContent\ConfigDocumentRepository;
use App\Services\PublicContent\Config\DatabasePublicContentConfigSource;
use Mockery;
use PHPUnit\Framework\TestCase;

final class DatabasePublicContentConfigSourceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_reads_nested_keys_from_associative_payload(): void
    {
        $source = $this->source([
            'widgets' => [
                'recirculation' => [
                    'page_types' => ['article', 'review', 'buying-guide'],
                ],
            ],
        ]);

        self::assertSame(
            ['article', 'review', 'buying-guide'],
            $source->get(1, 'widgets.recirculation.page_types', ['*']),
        );
    }

    public function test_normalises_entry_list_payloads_for_dot_lookup(): void
    {
        $source = $this->source([
            [
                'id' => 'w1',
                'key' => 'widgets',
                'value' => [
                    'recirculation' => [
                        'page_types' => ['article', 'review', 'buying-guide'],
                    ],
                    'comments' => [
                        'page_types' => ['article'],
                    ],
                ],
            ],
        ]);

        self::assertSame(
            ['article', 'review', 'buying-guide'],
            $source->get(1, 'widgets.recirculation.page_types', ['*']),
        );
        self::assertSame(['article'], $source->get(1, 'widgets.comments.page_types', ['*']));
    }

    public function test_missing_key_returns_caller_default(): void
    {
        $source = $this->source([
            'widgets' => [
                'comments' => ['page_types' => ['article']],
            ],
        ]);

        self::assertSame(['*'], $source->get(1, 'widgets.recirculation.page_types', ['*']));
    }

    /** @param array<mixed> $payload */
    private function source(array $payload): DatabasePublicContentConfigSource
    {
        $document = Mockery::mock(ConfigDocument::class)->makePartial();
        $document->payload = $payload;

        $repo = Mockery::mock(ConfigDocumentRepository::class);
        $repo->shouldReceive('findByType')->once()->with('public_content', 1)->andReturn($document);

        return new DatabasePublicContentConfigSource($repo);
    }
}
