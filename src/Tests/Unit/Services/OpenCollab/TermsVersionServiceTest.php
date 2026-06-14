<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\TermsVersionStatus;
use App\Framework\Database\Database;
use App\Models\TermsVersion;
use App\Repositories\OpenCollab\TermsVersionRepository;
use App\Services\OpenCollab\OpenCollabDocumentService;
use App\Services\OpenCollab\TermsVersionService;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

class TermsVersionServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_create_draft_persists_expected_fields(): void
    {
        $repository = Mockery::mock(TermsVersionRepository::class);
        $documents = Mockery::mock(OpenCollabDocumentService::class);
        $database = Mockery::mock(Database::class);
        $service = new TermsVersionService($repository, $documents, $database);
        $draft = $this->terms(['id' => 1, 'status' => TermsVersionStatus::Draft->value]);

        $database->shouldReceive('transaction')->once()->andReturnUsing(fn(callable $callback) => $callback());
        $repository->shouldReceive('create')->once()->with(Mockery::subset([
            'site_id' => 10,
            'semantic_version' => '1.0.0',
            'title' => 'Contributor Terms',
            'source_content' => '<p>Terms body long enough for validation.</p>',
            'status' => TermsVersionStatus::Draft->value,
            'is_material_change' => true,
            'created_by_user_id' => 99,
        ]))->andReturn($draft);

        $result = $service->createDraft(
            10,
            '1.0.0',
            'Contributor Terms',
            '<p>Terms body long enough for validation.</p>',
            99,
            ['is_material_change' => true]
        );

        $this->assertSame($draft, $result);
    }

    public function test_publish_hashes_rendered_snapshot_and_archives_previous_version(): void
    {
        $repository = Mockery::mock(TermsVersionRepository::class);
        $documents = Mockery::mock(OpenCollabDocumentService::class);
        $database = Mockery::mock(Database::class);
        $service = new TermsVersionService($repository, $documents, $database);

        $current = $this->terms(['id' => 1, 'site_id' => 10, 'status' => TermsVersionStatus::Published->value]);
        $draft = $this->terms([
            'id' => 2,
            'site_id' => 10,
            'source_content' => '<p>Updated terms</p>',
            'source_format' => 'html',
            'status' => TermsVersionStatus::Draft->value,
        ]);

        $database->shouldReceive('transaction')->once()->andReturnUsing(fn(callable $callback) => $callback());
        $repository->shouldReceive('latestPublishedForSite')->once()->with(10)->andReturn($current);
        $current->shouldReceive('update')->once()->with(Mockery::subset([
            'status' => TermsVersionStatus::Archived->value,
            'archived_by_user_id' => 99,
        ]));
        $draft->shouldReceive('update')->once()->with(Mockery::on(function (array $attributes): bool {
            return $attributes['rendered_content'] === '<p>Updated terms</p>'
                && $attributes['rendered_hash'] === hash('sha256', '<p>Updated terms</p>')
                && $attributes['status'] === TermsVersionStatus::Published->value
                && $attributes['published_by_user_id'] === 99;
        }));
        $draft->shouldReceive('fresh')->once()->andReturn($draft);

        $this->assertSame($draft, $service->publish($draft, 99));
    }

    public function test_update_rejects_published_terms(): void
    {
        $service = new TermsVersionService(
            Mockery::mock(TermsVersionRepository::class),
            Mockery::mock(OpenCollabDocumentService::class),
            Mockery::mock(Database::class),
        );
        $published = $this->terms(['status' => TermsVersionStatus::Published->value]);

        $this->expectException(RuntimeException::class);
        $service->updateDraft($published, ['title' => 'Changed']);
    }

    public function test_accept_records_exact_published_hash(): void
    {
        $repository = Mockery::mock(TermsVersionRepository::class);
        $service = new TermsVersionService(
            $repository,
            Mockery::mock(OpenCollabDocumentService::class),
            Mockery::mock(Database::class),
        );
        $terms = $this->terms([
            'id' => 5,
            'site_id' => 10,
            'status' => TermsVersionStatus::Published->value,
            'rendered_hash' => 'abc123',
        ]);
        $acceptance = Mockery::mock('App\\Models\\UserTermsAcceptance');

        $repository->shouldReceive('recordAcceptance')->once()->with(Mockery::subset([
            'site_id' => 10,
            'user_id' => 20,
            'terms_version_id' => 5,
            'rendered_hash' => 'abc123',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'accepted_via' => 'onboarding',
        ]))->andReturn($acceptance);

        $this->assertSame($acceptance, $service->accept($terms, 20, '127.0.0.1', 'PHPUnit'));
    }

    private function terms(array $attributes): TermsVersion
    {
        $reflection = new ReflectionClass(TermsVersion::class);
        $model = $reflection->newInstanceWithoutConstructor();
        $model->forceFill($attributes);

        return Mockery::mock($model)->makePartial();
    }
}
