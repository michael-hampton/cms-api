<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Models\Author;
use App\Models\ContributorAuthorSyncAudit;
use App\Models\ContributorProfile;
use App\Repositories\Cms\AuthorRepository;
use App\Repositories\OpenCollab\ContributorAuthorSyncAuditRepository;
use App\Repositories\OpenCollab\ContributorProfileRepository;
use App\Services\OpenCollab\ContributorAuthorSyncService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ContributorAuthorSyncServiceTest extends TestCase
{
    private MockInterface|ContributorProfileRepository $profileRepository;
    private MockInterface|AuthorRepository $authorRepository;
    private MockInterface|ContributorAuthorSyncAuditRepository $auditRepository;
    private ContributorAuthorSyncService $service;

    public function test_sync_skips_overridden_fields_and_updates_non_overridden_fields(): void
    {
        $profile = $this->makeProfile([
            'id' => 10,
            'user_id' => 5,
            'author_id' => 20,
            'bio' => 'Contributor bio',
            'avatar' => '/uploads/new.jpg',
            'account_status' => 'approved',
        ]);
        $author = $this->makeAuthor([
            'id' => 20,
            'site_id' => 3,
            'bio' => 'Admin bio',
            'avatar' => '/uploads/old.jpg',
            'overridden_fields' => [
                'bio' => ['by_type' => 'admin', 'by_id' => 99, 'at' => '2026-06-11 12:00:00'],
            ],
        ]);
        $updatedAuthor = $this->makeAuthor([
            'id' => 20,
            'site_id' => 3,
            'bio' => 'Admin bio',
            'avatar' => '/uploads/new.jpg',
            'overridden_fields' => $author->overridden_fields,
        ]);

        $this->authorRepository->shouldReceive('find')->once()->with(20)->andReturn($author);
        $this->authorRepository
            ->shouldReceive('update')
            ->once()
            ->with(20, Mockery::on(fn(array $data) => $data['avatar'] === '/uploads/new.jpg' && !array_key_exists('bio', $data)))
            ->andReturn($updatedAuthor);
        $this->profileRepository
            ->shouldReceive('update')
            ->once()
            ->with(10, Mockery::on(fn(array $data) => $data['author_sync_status'] === 'partially_synced'))
            ->andReturn($profile);
        $this->auditRepository
            ->shouldReceive('log')
            ->once()
            ->with(10, 20, 3, 'contributor', 5, 'profile_synced', Mockery::any(), Mockery::on(
                fn(array $metadata) => $metadata['skipped_overridden_fields'] === ['bio']
            ))
            ->andReturn($this->makeAuditModel());

        $this->service->syncProfileToAuthor($profile, 3, 'contributor', 5, ['bio', 'avatar']);
        $this->addToAssertionCount(1);
    }

    public function test_remove_override_resyncs_field_from_profile(): void
    {
        $author = $this->makeAuthor([
            'id' => 20,
            'site_id' => 3,
            'bio' => 'Admin bio',
            'overridden_fields' => [
                'bio' => ['by_type' => 'admin', 'by_id' => 99, 'at' => '2026-06-11 12:00:00'],
            ],
        ]);
        $profile = $this->makeProfile([
            'id' => 10,
            'author_id' => 20,
            'bio' => 'Contributor bio',
        ]);
        $updatedAuthor = $this->makeAuthor([
            'id' => 20,
            'site_id' => 3,
            'bio' => 'Contributor bio',
            'overridden_fields' => [],
        ]);

        $this->authorRepository->shouldReceive('find')->once()->with(20)->andReturn($author);
        $this->profileRepository->shouldReceive('findByAuthorId')->once()->with(20)->andReturn($profile);
        $this->authorRepository
            ->shouldReceive('update')
            ->once()
            ->with(20, Mockery::on(fn(array $data) => $data['bio'] === 'Contributor bio' && $data['overridden_fields'] === []))
            ->andReturn($updatedAuthor);
        $this->auditRepository
            ->shouldReceive('log')
            ->once()
            ->with(10, 20, 3, 'admin', 99, 'override_removed', ['bio'])
            ->andReturn($this->makeAuditModel());

        $result = $this->service->removeOverride(20, 'bio', 99);

        $this->assertSame($updatedAuthor, $result);
    }

    public function test_admin_update_marks_submitted_public_fields_as_overridden(): void
    {
        $author = $this->makeAuthor([
            'id' => 20,
            'site_id' => 3,
            'overridden_fields' => [],
        ]);
        $updatedAuthor = $this->makeAuthor([
            'id' => 20,
            'site_id' => 3,
            'overridden_fields' => [
                'bio' => ['by_type' => 'admin', 'by_id' => 99],
            ],
        ]);

        $this->authorRepository
            ->shouldReceive('update')
            ->once()
            ->with(20, Mockery::on(fn(array $data) => isset($data['overridden_fields']['bio']) && !isset($data['overridden_fields']['payment_details'])))
            ->andReturn($updatedAuthor);
        $this->profileRepository->shouldReceive('findByAuthorId')->once()->with(20)->andReturnNull();
        $this->auditRepository
            ->shouldReceive('log')
            ->once()
            ->with(null, 20, 3, 'admin', 99, 'admin_override', ['bio'])
            ->andReturn($this->makeAuditModel());

        $result = $this->service->recordAdminAuthorUpdate($author, [
            'bio' => 'Admin bio',
            'payment_details' => 'never-public',
        ], 99);

        $this->assertSame($updatedAuthor, $result);
    }

    public function test_sync_updates_all_social_links_from_profile(): void
    {
        $profile = $this->makeProfile([
            'id' => 10,
            'user_id' => 5,
            'author_id' => 20,
            'account_status' => 'approved',
            'linkedin_url' => 'https://linkedin.com/in/example',
            'twitter_url' => 'https://x.com/example',
            'instagram_url' => 'https://instagram.com/example',
            'tiktok_url' => 'https://tiktok.com/@example',
        ]);
        $author = $this->makeAuthor([
            'id' => 20,
            'site_id' => 3,
            'overridden_fields' => [],
        ]);

        $this->authorRepository->shouldReceive('find')->once()->with(20)->andReturn($author);
        $this->authorRepository
            ->shouldReceive('update')
            ->once()
            ->with(20, Mockery::on(fn(array $data) =>
                $data['linkedin'] === 'https://linkedin.com/in/example'
                && $data['twitter'] === 'https://x.com/example'
                && $data['instagram'] === 'https://instagram.com/example'
                && $data['tiktok'] === 'https://tiktok.com/@example'
            ))
            ->andReturn($author);
        $this->profileRepository
            ->shouldReceive('update')
            ->once()
            ->with(10, Mockery::on(fn(array $data) => $data['author_sync_status'] === 'synced'))
            ->andReturn($profile);
        $this->auditRepository
            ->shouldReceive('log')
            ->once()
            ->with(10, 20, 3, 'contributor', 5, 'profile_synced', Mockery::on(fn(array $fields) =>
                in_array('linkedin', $fields, true)
                && in_array('twitter', $fields, true)
                && in_array('instagram', $fields, true)
                && in_array('tiktok', $fields, true)
            ), Mockery::any())
            ->andReturn($this->makeAuditModel());

        $this->service->syncProfileToAuthor($profile, 3, 'contributor', 5, [
            'linkedin_url',
            'twitter_url',
            'instagram_url',
            'tiktok_url',
        ]);
        $this->addToAssertionCount(1);
    }

    private function makeProfile(array $attributes): ContributorProfile
    {
        $profile = (new \ReflectionClass(ContributorProfile::class))->newInstanceWithoutConstructor();

        foreach ($attributes as $key => $value) {
            $profile->{$key} = $value;
        }

        return $profile;
    }

    private function makeAuthor(array $attributes): Author
    {
        $author = (new \ReflectionClass(Author::class))->newInstanceWithoutConstructor();

        foreach ($attributes as $key => $value) {
            $author->{$key} = $value;
        }

        return $author;
    }

    private function makeAuditModel(): ContributorAuthorSyncAudit
    {
        return (new \ReflectionClass(ContributorAuthorSyncAudit::class))->newInstanceWithoutConstructor();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->profileRepository = Mockery::mock(ContributorProfileRepository::class);
        $this->authorRepository = Mockery::mock(AuthorRepository::class);
        $this->auditRepository = Mockery::mock(ContributorAuthorSyncAuditRepository::class);

        $this->service = new ContributorAuthorSyncService(
            $this->profileRepository,
            $this->authorRepository,
            $this->auditRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
