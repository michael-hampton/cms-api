<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\UploadedFile;
use App\Models\ContributorProfile;
use App\Repositories\OpenCollab\ContributorProfileRepository;
use App\Services\Cms\ImageUploadService;
use App\Services\OpenCollab\ContributorProfileService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ContributorProfileServiceTest extends TestCase
{
    private MockInterface|ContributorProfileRepository $profileRepo;
    private MockInterface|ImageUploadService $uploadService;
    private ContributorProfileService $service;

    public function test_uploadAvatar_creates_profile_when_none_exists(): void
    {
        $file = $this->makeValidAvatarFile();

        $this->profileRepo
            ->shouldReceive('findByUserId')
            ->once()
            ->with(1)
            ->andReturnNull();

        $this->uploadService
            ->shouldReceive('uploadToPath')
            ->once()
            ->with($file, 'open-collab/avatars', null)
            ->andReturn('open-collab/avatars/test.jpg');

        $this->profileRepo
            ->shouldReceive('createForUser')
            ->once()
            ->with(1, ['avatar' => '/uploads/open-collab/avatars/test.jpg'])
            ->andReturn($this->makeProfile(['avatar' => '/open-collab/avatars/test.jpg']));

        $url = $this->service->uploadAvatar(1, 10, $file);

        $this->assertSame('/uploads/open-collab/avatars/test.jpg', $url);
    }

    private function makeValidAvatarFile(): MockInterface|UploadedFile
    {
        return $this->makeAvatarFile();
    }

    // ── uploadAvatar ──────────────────────────────────────────────────────────

    private function makeAvatarFile(
        string $mimeType = 'image/jpeg',
        int    $size = 1024 * 500,
        bool   $isValid = true,
    ): MockInterface|UploadedFile
    {
        $mock = Mockery::mock(UploadedFile::class);
        $mock->shouldReceive('isValid')->andReturn($isValid);
        $mock->shouldReceive('getMimeType')->andReturn($mimeType);
        $mock->shouldReceive('getSize')->andReturn($size);

        return $mock;
    }

    private function makeProfile(array $attributes = []): MockInterface|ContributorProfile
    {
        $mock = Mockery::mock(ContributorProfile::class)->makePartial();

        foreach ($attributes as $key => $value) {
            $mock->{$key} = $value;
        }

        return $mock;
    }

    public function test_uploadAvatar_replaces_old_avatar_on_existing_profile(): void
    {
        $file = $this->makeValidAvatarFile();
        $profile = $this->makeProfile(['id' => 5, 'avatar' => '/old/avatar.jpg']);

        $this->profileRepo
            ->shouldReceive('findByUserId')
            ->once()
            ->andReturn($profile);

        $this->uploadService
            ->shouldReceive('uploadToPath')
            ->once()
            ->with($file, 'open-collab/avatars', '/old/avatar.jpg')
            ->andReturn('open-collab/avatars/new.jpg');

        $this->profileRepo
            ->shouldReceive('update')
            ->once()
            ->with(5, ['avatar' => '/uploads/open-collab/avatars/new.jpg'])
            ->andReturn($profile);

        $url = $this->service->uploadAvatar(1, 10, $file);

        $this->assertSame('/uploads/open-collab/avatars/new.jpg', $url);
    }

    public function test_uploadAvatar_throws_on_invalid_mime_type(): void
    {
        $file = $this->makeAvatarFile(mimeType: 'image/gif');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Only JPG, PNG, and WebP images are accepted.');

        $this->service->uploadAvatar(1, 10, $file);
    }

    public function test_uploadAvatar_throws_when_file_exceeds_2mb(): void
    {
        $file = $this->makeAvatarFile(size: 3 * 1024 * 1024);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Avatar image must be under 2 MB.');

        $this->service->uploadAvatar(1, 10, $file);
    }

    // ── removeAvatar ──────────────────────────────────────────────────────────

    public function test_uploadAvatar_throws_when_file_is_invalid(): void
    {
        $file = $this->makeAvatarFile(isValid: false);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid file upload.');

        $this->service->uploadAvatar(1, 10, $file);
    }

    public function test_removeAvatar_deletes_file_and_nulls_db(): void
    {
        $profile = $this->makeProfile(['id' => 7, 'avatar' => '/avatars/foo.jpg']);

        $this->profileRepo
            ->shouldReceive('findByUserId')
            ->once()
            ->andReturn($profile);

        $this->uploadService
            ->shouldReceive('delete')
            ->once()
            ->with('avatars/foo.jpg');

        $this->profileRepo
            ->shouldReceive('update')
            ->once()
            ->with(7, ['avatar' => null])
            ->andReturn($profile);

        $this->service->removeAvatar(1, 10); // no exception = pass
        $this->addToAssertionCount(1);
    }

    public function test_removeAvatar_is_noop_when_no_profile(): void
    {
        $this->profileRepo
            ->shouldReceive('findByUserId')
            ->once()
            ->andReturnNull();

        $this->uploadService->shouldNotReceive('delete');
        $this->profileRepo->shouldNotReceive('update');

        $this->service->removeAvatar(1, 10);
        $this->addToAssertionCount(1);
    }

    // ── saveExpertise ─────────────────────────────────────────────────────────

    public function test_removeAvatar_is_noop_when_avatar_already_null(): void
    {
        $profile = $this->makeProfile(['id' => 3, 'avatar' => null]);

        $this->profileRepo
            ->shouldReceive('findByUserId')
            ->once()
            ->andReturn($profile);

        $this->uploadService->shouldNotReceive('delete');
        $this->profileRepo->shouldNotReceive('update');

        $this->service->removeAvatar(1, 10);
        $this->addToAssertionCount(1);
    }

    public function test_saveExpertise_persists_tags_for_existing_profile(): void
    {
        $profile = $this->makeProfile(['id' => 4, 'expertise' => '']);
        $fresh = $this->makeProfile(['id' => 4, 'expertise' => 'Technology,Finance']);

        $this->profileRepo
            ->shouldReceive('findByUserId')
            ->once()
            ->andReturn($profile);

        $profile->shouldReceive('fresh')
            ->once()
            ->andReturn($fresh);

        $this->profileRepo
            ->shouldReceive('update')
            ->once()
            ->with(4, ['expertise' => 'Technology,Finance'])
            ->andReturn($profile);

        $result = $this->service->saveExpertise(1, 10, ['Technology', 'Finance']);

        $this->assertSame('Technology,Finance', $result->expertise);
    }

    public function test_saveExpertise_creates_profile_when_none_exists(): void
    {
        $created = $this->makeProfile(['id' => 9, 'expertise' => 'Health']);

        $this->profileRepo
            ->shouldReceive('findByUserId')
            ->once()
            ->andReturnNull();

        $this->profileRepo
            ->shouldReceive('createForUser')
            ->once()
            ->with(1, ['expertise' => 'Health'])
            ->andReturn($created);

        $result = $this->service->saveExpertise(1, 10, ['Health']);

        $this->assertSame('Health', $result->expertise);
    }

    public function test_saveExpertise_deduplicates_tags(): void
    {
        $profile = $this->makeProfile(['id' => 4, 'expertise' => '']);
        $fresh = $this->makeProfile(['id' => 4, 'expertise' => 'Tech']);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $profile->shouldReceive('fresh')->andReturn($fresh);

        $this->profileRepo
            ->shouldReceive('update')
            ->once()
            ->with(4, ['expertise' => 'Tech'])
            ->andReturn($profile);

        $this->service->saveExpertise(1, 10, ['Tech', 'Tech', 'Tech']);
        $this->addToAssertionCount(1);
    }

    public function test_saveExpertise_strips_empty_strings(): void
    {
        $profile = $this->makeProfile(['id' => 4, 'expertise' => '']);
        $fresh = $this->makeProfile(['id' => 4, 'expertise' => 'Finance']);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $profile->shouldReceive('fresh')->andReturn($fresh);

        $this->profileRepo
            ->shouldReceive('update')
            ->once()
            ->with(4, ['expertise' => 'Finance'])
            ->andReturn($profile);

        $this->service->saveExpertise(1, 10, ['', 'Finance', '  ']);
        $this->addToAssertionCount(1);
    }

    public function test_saveExpertise_throws_when_more_than_8_tags(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('maximum of 8');

        $this->service->saveExpertise(1, 10, [
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I',
        ]);
    }

    // ── updateProfile ─────────────────────────────────────────────────────────

    public function test_saveExpertise_throws_when_tag_exceeds_40_chars(): void
    {
        $longTag = str_repeat('x', 41);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('exceeds the maximum length of 40');

        $this->service->saveExpertise(1, 10, [$longTag]);
    }

    public function test_updateProfile_updates_bio_on_existing_profile(): void
    {
        $profile = $this->makeProfile(['id' => 2, 'bio' => 'Old bio', 'avatar' => null]);
        $fresh = $this->makeProfile(['id' => 2, 'bio' => 'New bio', 'avatar' => null]);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $profile->shouldReceive('fresh')->andReturn($fresh);

        $this->profileRepo
            ->shouldReceive('update')
            ->once()
            ->with(2, ['bio' => 'New bio'])
            ->andReturn($profile);

        $result = $this->service->updateProfile(1, 10, ['bio' => 'New bio']);

        $this->assertSame('New bio', $result->bio);
    }

    public function test_updateProfile_removes_avatar_when_empty_string_given(): void
    {
        $profile = $this->makeProfile(['id' => 2, 'bio' => null, 'avatar' => '/old.jpg']);
        $fresh = $this->makeProfile(['id' => 2, 'bio' => null, 'avatar' => null]);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $profile->shouldReceive('fresh')->andReturn($fresh);

        $this->uploadService
            ->shouldReceive('delete')
            ->once()
            ->with('old.jpg');

        $this->profileRepo
            ->shouldReceive('update')
            ->once()
            ->with(2, ['avatar' => null])
            ->andReturn($profile);

        $result = $this->service->updateProfile(1, 10, ['avatar' => '']);

        $this->assertNull($result->avatar);
    }

    public function test_updateProfile_throws_when_bio_exceeds_1000_chars(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Bio must be 1000 characters or fewer.');

        $this->service->updateProfile(1, 10, ['bio' => str_repeat('a', 1001)]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function test_updateProfile_ignores_keys_not_in_payload(): void
    {
        // Only 'bio' provided — 'avatar' key absent means no avatar change
        $profile = $this->makeProfile(['id' => 2, 'bio' => '', 'avatar' => '/keep.jpg']);
        $fresh = $this->makeProfile(['id' => 2, 'bio' => 'Hi', 'avatar' => '/keep.jpg']);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $profile->shouldReceive('fresh')->andReturn($fresh);

        $this->profileRepo
            ->shouldReceive('update')
            ->once()
            ->with(2, ['bio' => 'Hi'])
            ->andReturn($profile);

        $this->uploadService->shouldNotReceive('delete');

        $result = $this->service->updateProfile(1, 10, ['bio' => 'Hi']);
        $this->assertSame('/keep.jpg', $result->avatar);
    }

    public function test_updateSampleLinks_normalises_and_persists_links(): void
    {
        $profile = $this->makeProfile(['id' => 12, 'bio' => 'Keep me']);
        $fresh = $this->makeProfile([
            'id' => 12,
            'bio' => 'Keep me',
            'sample_links' => [
                [
                    'url' => 'https://example.com/first',
                    'title' => 'First',
                    'description' => 'Context',
                    'sort_order' => 1,
                ],
                [
                    'url' => 'https://example.com/second',
                    'title' => null,
                    'description' => null,
                    'sort_order' => 2,
                ],
            ],
        ]);

        $this->profileRepo
            ->shouldReceive('findOrCreateForUserAndSite')
            ->once()
            ->with(1, 10)
            ->andReturn($profile);

        $profile->shouldReceive('fresh')->once()->andReturn($fresh);

        $this->profileRepo
            ->shouldReceive('update')
            ->once()
            ->with(12, [
                'sample_links' => [
                    [
                        'url' => 'https://example.com/first',
                        'title' => 'First',
                        'description' => 'Context',
                        'sort_order' => 1,
                    ],
                    [
                        'url' => 'https://example.com/second',
                        'title' => null,
                        'description' => null,
                        'sort_order' => 2,
                    ],
                ],
            ])
            ->andReturn($profile);

        $result = $this->service->updateSampleLinks(1, 10, [
            ['url' => '', 'title' => '', 'description' => ''],
            ['url' => ' https://example.com/first ', 'title' => ' First ', 'description' => ' Context '],
            ['url' => 'https://example.com/second', 'title' => ' ', 'description' => ''],
        ]);

        $this->assertSame('Keep me', $result->bio);
        $this->assertSame(2, $result->sample_links[1]['sort_order']);
    }

    public function test_updateSampleLinks_rejects_non_http_urls(): void
    {
        $this->profileRepo->shouldNotReceive('findOrCreateForUserAndSite');
        $this->profileRepo->shouldNotReceive('update');

        $this->expectException(ValidationException::class);

        $this->service->updateSampleLinks(1, 10, [
            ['url' => 'ftp://example.com/file', 'title' => 'Bad'],
        ]);
    }

    public function test_updateSampleLinks_rejects_more_than_five_links(): void
    {
        $this->profileRepo->shouldNotReceive('findOrCreateForUserAndSite');
        $this->profileRepo->shouldNotReceive('update');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('maximum of 5');

        $this->service->updateSampleLinks(1, 10, [
            ['url' => 'https://example.com/1'],
            ['url' => 'https://example.com/2'],
            ['url' => 'https://example.com/3'],
            ['url' => 'https://example.com/4'],
            ['url' => 'https://example.com/5'],
            ['url' => 'https://example.com/6'],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->profileRepo = Mockery::mock(ContributorProfileRepository::class);
        $this->uploadService = Mockery::mock(ImageUploadService::class);

        $this->service = new ContributorProfileService(
            $this->profileRepo,
            $this->uploadService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
