<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Author;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class AuthorControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsAuthorsList()
    {
        $this->createAuthor();

        $response = $this->getForSite('/api/authors');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(1, $data['items']);
    }

    public function testIndexWithSearchCriteria()
    {
        $this->createAuthor(['name' => 'John Doe']);
        $this->createAuthor(['name' => 'Jane Smith']);

        $response = $this->getForSite('/api/authors?status=active');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['items']);
        $this->assertEquals('Jane Smith', $data['items'][0]['name']);
    }

    public function testStoreCreatesNewAuthor()
    {
        $authorData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'bio' => 'Test bio',
            'status' => 'active'
        ];

        $response = $this->postForSite('/api/authors', $authorData);

        $this->assertEquals(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('author', $data['data']);
        $this->assertEquals('John Doe', $data['data']['author']['name']);
        $this->assertEquals('john-doe', $data['data']['author']['slug']);
    }

    public function testStoreWithAvatarFile()
    {
        $authorData = [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ];
        $files = [
            'avatar' => $this->createUploadedFile('avatar.jpg', 'image/jpeg')
        ];

        $response = $this->postForSite('/api/authors', $authorData, $files);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertNotEmpty($data['data']['author']['avatar']);
    }

    public function testStoreValidatesRequiredFields()
    {
        $response = $this->postForSite('/api/authors', []);

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('name', $data['errors']);
    }

    public function testStoreValidatesUniqueEmail()
    {
        $this->createAuthor(['email' => 'john@example.com']);

        $response = $this->postForSite('/api/authors', [
            'name' => 'Jane Doe',
            'email' => 'john@example.com'
        ]);

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('email', $data['errors']);
    }

    public function testShowReturnsAuthorById()
    {
        $author = $this->createAuthor(['name' => 'John Doe']);

        $response = $this->getForSite("/api/authors/{$author->id}");


        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('John Doe', $data['data']['author']['name']);
    }

    public function testShowReturnsAuthorBySlug()
    {
        $this->createAuthor(['slug' => 'john-doe', 'name' => 'John Doe']);

        $response = $this->getForSite('/api/authors/john-doe');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('John Doe', $data['data']['author']['name']);
    }

    public function testShowReturns404ForNonexistentAuthor()
    {
        $response = $this->getForSite('/api/authors/999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateModifiesAuthor()
    {
        $author = $this->createAuthor();

        $response = $this->putForSite("/api/authors/{$author->id}", [
            'name' => 'John Updated',
            'email' => 'john@example.com',
            'bio' => 'Updated bio'
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('John Updated', $data['data']['author']['name']);
        $this->assertEquals('Updated bio', $data['data']['author']['bio']);
    }

//    public function testUpdateWithAvatarFile()
//    {
//        $this->cleanupDatabase();
//
//        $author = Author::create([
//            'name' => 'John Doe',
//            'slug' => 'john-doe',
//            'email' => 'john@example.com',
//            'status' => 'active'
//        ]);
//
//        $files = [
//            'avatar' => $this->createUploadedFile('new-avatar.jpg', 'image/jpeg')
//        ];
//
//        $response = $this->put("/api/authors/{$author->id}", ['name' => 'John Doe', 'email' => 'john@example.com'], $files);
//
//        echo '<pre>';
//        print_r($response->getContent());
//        die;
//
//        $this->assertEquals(200, $response->getStatusCode());
//        $data = json_decode($response->getContent(), true);
//        $this->assertNotEmpty($data['author']['avatar']);
//    }

    public function testDestroyDeletesAuthor()
    {
        $author = $this->createAuthor();

        $response = $this->deleteForSite("/api/authors/{$author->id}?reassignId=1");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull(Author::find($author->id));
    }

    public function testDestroyReturns404ForNonexistentAuthor()
    {
        $response = $this->deleteForSite('/api/authors/999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGetActiveReturnsOnlyActiveAuthors()
    {
        $this->createAuthor(['status' => 'active', 'name' => 'Active Author']);
        $this->createAuthor(['status' => 'inactive']);

        $response = $this->getForSite('/api/authors/active');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['data']['authors']);
        $this->assertEquals('Active Author', $data['data']['authors'][0]['name']);
    }

    public function testMergeAuthors()
    {
        $source = $this->createAuthor();
        $target = $this->createAuthor();

        $response = $this->postForSite('/api/authors/merge', [
            'source_author_id' => $source->id,
            'target_author_id' => $target->id
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull(Author::find($source->id));
    }

    public function testMergeValidatesRequiredIds()
    {
        $response = $this->postForSite('/api/authors/merge', [
            'source_author_id' => 1
        ]);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCheckDeleteReturnsCanDeleteWhenNoPagesExist()
    {
        // Arrange: create an author with no pages
        $author = $this->createAuthor();

        // Act
        $response = $this->getForSite("/api/authors/{$author->id}/check-delete");

        // Assert
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('can_delete', $data['data']);
        $this->assertTrue($data['data']['can_delete']);
        $this->assertEquals(0, $data['data']['pages_count']);
        $this->assertFalse($data['data']['requires_reassignment']);
    }

    public function testCheckDeleteReturnsRequiresReassignmentWhenPagesExist()
    {
        // Arrange: create an author that has pages
        $author = $this->createAuthor();
        // Create one or more pages for this author
        $page = $this->createPage();

        $this->attachAuthorToPage($page, $author);

        // Act
        $response = $this->getForSite("/api/authors/{$author->id}/check-delete");

        // Assert
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['requires_reassignment']);
        $this->assertGreaterThan(0, $data['data']['pages_count']);
        $this->assertArrayHasKey('alternatives', $data['data']);
        $this->assertIsArray($data['data']['alternatives']);
    }

    public function testCheckDeleteReturns404WhenAuthorNotFound()
    {
        // Act
        $response = $this->getForSite('/api/authors/9999/check-delete');

        // Assert
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Author not found', $data['data']['message']);
    }

    public function testDuplicateAuthorSuccessfully(): void
    {
        // Create original author
        $author = $this->createAuthor(['name' => 'John Doe', 'bio' => 'Original author bio', 'website' => 'https://johndoe.com', 'status' => 'active']);;

        // Duplicate the author
        $response = $this->postForSite("/api/authors/duplicate/{$author->id}");

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        // Verify response structure
        $this->assertArrayHasKey('data', $data);
        $duplicated = $data['data'];

        // Verify duplicated author data
        $this->assertEquals('John Doe (Copy)', $duplicated['name']);
        $this->assertNull($duplicated['email']); // Email should be cleared
        $this->assertEquals('Original author bio', $duplicated['bio']);
        $this->assertEquals('https://johndoe.com', $duplicated['website']);
        $this->assertEquals('inactive', $duplicated['status']);
        $this->assertNotEquals($author->id, $duplicated['id']);
        $this->assertNotEquals($author->slug, $duplicated['slug']);

        // Verify both authors exist in database
        $this->assertEquals(2, Author::count());
    }

    public function testDuplicateAuthorWithCustomName(): void
    {
        $author = $this->createAuthor();

        $response = $this->postForSite("/api/authors/duplicate/{$author->id}", [
            'name' => 'Jane Smith - Editor'
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Jane Smith - Editor', $data['data']['name']);
        $this->assertEquals('jane-smith-editor', $data['data']['slug']);
    }

    public function testDuplicateAuthorWithAvatar(): void
    {
        // Create author with avatar
        $author = $this->createAuthor(['avatar' => 'uploads/avatars/bob.jpg']);

        // Create dummy avatar file
        $avatarPath = 'uploads/avatars/bob.jpg';

        $this->createFile($avatarPath);

        $response = $this->postForSite("/api/authors/duplicate/{$author->id}", [], [], [], true);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        // Verify avatar was duplicated
        $this->assertNotNull($data['data']['avatar']);
        $this->assertNotEquals('avatars/bob.jpg', $data['data']['avatar']);

        // Cleanup
        @unlink($avatarPath);
    }

    public function testDuplicateNonExistentAuthor(): void
    {
        $response = $this->postJson('/api/duplicate/author/99999');

        $this->assertResponseStatus(404, $response);
    }

    public function testBulkDeleteSuccessfully(): void
    {
        $author1 = $this->createAuthor();
        $author2 = $this->createAuthor();

        $response = $this->postForSite('/api/authors/bulk-delete', [
            'ids' => [$author1->id, $author2->id]
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['result']['deleted']);
        $this->assertCount(0, $data['result']['failed']);

        $this->assertNull(Author::find($author1->id));
        $this->assertNull(Author::find($author2->id));
    }
}