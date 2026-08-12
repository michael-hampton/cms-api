<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Enums\OpenCollab\OpenCollabImageRights;
use App\Models\Image;
use App\Models\OpenCollabPermission;
use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ImageLibraryControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private User $contributor;
    private User $otherContributor;
    private User $unauthorised;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Image library permission slugs are not in config/rbac.php; seed once
        // outside the per-test transaction so setUp only reads them.
        foreach ([
            ['Browse Own Images', 'images.browse_own', 'images'],
            ['Use Own Images', 'images.use_own', 'images'],
            ['Use Shared Images', 'images.use_shared', 'images'],
            ['Upload Images', 'images.upload', 'images'],
        ] as [$name, $slug, $group]) {
            if (OpenCollabPermission::query()->where('slug', $slug)->first() !== null) {
                continue;
            }
            OpenCollabPermission::create([
                'name' => $name,
                'slug' => $slug,
                'group' => $group,
            ]);
        }
    }

    public function test_unauthenticated_user_cannot_browse_image_library(): void
    {
        $response = $this->getForSiteUnauthenticated('/api/open-collab/images');

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_user_without_image_permissions_cannot_browse_image_library(): void
    {
        $this->actingAs($this->unauthorised);

        $response = $this->getForSite('/api/open-collab/images');

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_contributor_sees_only_their_own_active_images_for_current_site(): void
    {
        $this->actingAs($this->contributor);
        $own = $this->createImage([
            'name' => 'My image',
            'image_rights' => OpenCollabImageRights::ContributorOwned->value,
        ]);

        $this->actingAs($this->otherContributor);
        $this->createImage([
            'name' => 'Other image',
            'image_rights' => OpenCollabImageRights::ContributorOwned->value,
        ]);

        $this->actingAs($this->contributor);
        $this->createImage([
            'name' => 'Inactive image',
            'is_active' => false,
        ]);

        $otherSiteId = $this->createSite()->id;
        $this->createImage([
            'name' => 'Other site image',
            'site_id' => $otherSiteId,
        ]);

        $response = $this->getForSite('/api/open-collab/images');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertCount(1, $data['items']);
        $this->assertEquals($own->id, $data['items'][0]['id']);
        $this->assertEquals('My image', $data['items'][0]['name']);
    }

    public function test_index_can_filter_by_image_rights(): void
    {
        $this->actingAs($this->contributor);

        $this->createImage([
            'name' => 'Owned image',
            'image_rights' => OpenCollabImageRights::ContributorOwned->value,
        ]);
        $agency = $this->createImage([
            'name' => 'Agency image',
            'image_rights' => OpenCollabImageRights::Agency->value,
        ]);

        $response = $this->getForSite(
            '/api/open-collab/images?image_rights=' . OpenCollabImageRights::Agency->value
        );
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $data['items']);
        $this->assertEquals($agency->id, $data['items'][0]['id']);
        $this->assertEquals(OpenCollabImageRights::Agency->value, $data['items'][0]['image_rights']);
    }

    public function test_index_rejects_invalid_image_rights(): void
    {
        $this->actingAs($this->contributor);

        $response = $this->getForSite('/api/open-collab/images?image_rights=not-valid');

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertStringContainsString('Invalid image_rights value', $response->getContent());
    }

    public function test_contributor_can_show_their_own_image(): void
    {
        $this->actingAs($this->contributor);
        $image = $this->createImage([
            'name' => 'Show me',
            'alt_text' => 'A test image',
        ]);

        $response = $this->getForSite("/api/open-collab/images/{$image->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($image->id, $data['data']['image']['id']);
        $this->assertEquals('Show me', $data['data']['image']['name']);
        $this->assertEquals('A test image', $data['data']['image']['alt_text']);
    }

    public function test_contributor_cannot_show_another_contributors_private_image(): void
    {
        $this->actingAs($this->otherContributor);
        $image = $this->createImage([
            'image_rights' => OpenCollabImageRights::ContributorOwned->value,
        ]);

        $this->actingAs($this->contributor);
        $response = $this->getForSite("/api/open-collab/images/{$image->id}");

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_show_returns_not_found_for_missing_image(): void
    {
        $this->actingAs($this->contributor);

        $response = $this->getForSite('/api/open-collab/images/999999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->contributor);
        $this->grantSitePermission($this->contributor, 'images.upload');

        $response = $this->postForSite(
            '/api/open-collab/images',
            [],
            [],
            ['Accept' => 'application/json'],
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_user_without_upload_permission_cannot_upload_image(): void
    {
        $this->actingAs($this->unauthorised);

        $response = $this->postForSite(
            '/api/open-collab/images',
            $this->validUploadPayload(),
            ['file' => $this->createUploadedFile('library-test.png', 'image/png')],
            ['Accept' => 'application/json'],
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_contributor_with_upload_permission_can_upload_image(): void
    {
        $this->actingAs($this->contributor);
        $this->grantSitePermission($this->contributor, 'images.upload');

        $response = $this->postForSite(
            '/api/open-collab/images',
            $this->validUploadPayload(),
            ['file' => $this->createUploadedFile('library-success.png', 'image/png')],
            ['Accept' => 'application/json'],
        );
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('Contributor upload', $data['image']['name']);
        $this->assertEquals(
            OpenCollabImageRights::StaffOwned->value,
            $data['image']['image_rights'],
        );

        $this->assertDatabaseHas('images', [
            'site_id' => $this->siteId,
            'name' => 'Contributor upload',
            'image_rights' => OpenCollabImageRights::StaffOwned->value,
        ]);
    }

    private function createImage(array $attributes = []): Image
    {
        return Image::create(array_merge([
            'site_id' => $this->siteId,
            'created_by' => $this->authenticatedUser?->id,
            'name' => 'Library image',
            'filename' => uniqid('image-', true) . '.jpg',
            'original_name' => 'image.jpg',
            'file_path' => '/uploads/image.jpg',
            'url' => '/uploads/image.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
            'width' => 1200,
            'height' => 800,
            'alt_text' => 'Image alt text',
            'credit' => '',
            'image_rights' => OpenCollabImageRights::StaffOwned->value,
            'is_active' => true,
            'is_archived' => false,
        ], $attributes));
    }

    private function validUploadPayload(): array
    {
        return [
            'name' => 'Contributor upload',
            'image_rights' => OpenCollabImageRights::StaffOwned->value,
            'alt_text' => 'Uploaded image alt text',
            'credit' => 'Open Collab contributor',
            'rights_confirmation' => true,
            'ai_generated' => false,
            'contains_music' => false,
            'sponsored_content' => false,
            'affiliate_content' => false,
            'unclear_rights' => false,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSiteExists();
        // Enable RBAC once; grantSitePermission used to re-seed the full catalogue
        // on every call (~2s each), which dominated this suite's runtime.
        $this->enableSiteRbac();

        $this->contributor = $this->createUser([
            'email' => 'image-library-contributor@example.com',
            'role' => 'contributor',
            'is_contributor' => true,
        ]);
        $this->grantSitePermission($this->contributor, 'images.browse_own');
        $this->grantSitePermission($this->contributor, 'images.use_own');

        $this->otherContributor = $this->createUser([
            'email' => 'image-library-other@example.com',
            'role' => 'contributor',
            'is_contributor' => true,
        ]);
        $this->grantSitePermission($this->otherContributor, 'images.browse_own');
        $this->grantSitePermission($this->otherContributor, 'images.use_own');

        // No grants → policy denies browse/upload. Explicit deny rows were redundant
        // and each triggered another expensive RBAC seed before memoization.
        $this->unauthorised = $this->createUser([
            'email' => 'image-library-no-permission@example.com',
            'role' => 'user',
            'is_contributor' => false,
        ]);
    }
}
