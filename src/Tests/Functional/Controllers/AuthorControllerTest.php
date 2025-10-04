<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Author;

class AuthorControllerTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }

    public function testIndexReturnsAuthorsList()
    {
        Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'email' => 'john@example.com',
            'status' => 'active'
        ]);

        $response = $this->get('/api/authors');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(1, $data['items']);
    }

    public function testIndexWithSearchCriteria()
    {
        Author::create(['name' => 'John Doe', 'slug' => 'john-doe', 'status' => 'active']);
        Author::create(['name' => 'Jane Smith', 'slug' => 'jane-smith', 'status' => 'inactive']);

        $response = $this->get('/api/authors?status=active');

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

        $response = $this->post('/api/authors', $authorData);

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

        $response = $this->post('/api/authors', $authorData, $files);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertNotEmpty($data['data']['author']['avatar']);
    }

    public function testStoreValidatesRequiredFields()
    {
        $response = $this->post('/api/authors', []);

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('name', $data['errors']);
    }

    public function testStoreValidatesUniqueEmail()
    {
        Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'email' => 'john@example.com',
            'status' => 'active'
        ]);

        $response = $this->post('/api/authors', [
            'name' => 'Jane Doe',
            'email' => 'john@example.com'
        ]);

        $this->assertEquals(422, $response->getStatusCode());
//        $data = json_decode($response->getContent(), true);
//        $this->assertStringContainsString('email', strtolower($data['message']));
    }

    public function testShowReturnsAuthorById()
    {
        $author = Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'email' => 'john@example.com',
            'status' => 'active'
        ]);

        $response = $this->get("/api/authors/{$author->id}");


        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('John Doe', $data['data']['author']['name']);
    }

    public function testShowReturnsAuthorBySlug()
    {
        Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'email' => 'john@example.com',
            'status' => 'active'
        ]);

        $response = $this->get('/api/authors/john-doe');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('John Doe', $data['data']['author']['name']);
    }

    public function testShowReturns404ForNonexistentAuthor()
    {
        $response = $this->get('/api/authors/999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateModifiesAuthor()
    {
        $author = Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'email' => 'john@example.com',
            'status' => 'active'
        ]);

        $response = $this->put("/api/authors/{$author->id}", [
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
        $author = Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'email' => 'john@example.com',
            'status' => 'active'
        ]);

        $response = $this->delete("/api/authors/{$author->id}?reassignId=1");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull(Author::find($author->id));
    }

    public function testDestroyReturns404ForNonexistentAuthor()
    {
        $response = $this->delete('/api/authors/999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGetActiveReturnsOnlyActiveAuthors()
    {
        Author::create(['name' => 'Active Author', 'slug' => 'active', 'status' => 'active']);
        Author::create(['name' => 'Inactive Author', 'slug' => 'inactive', 'status' => 'inactive']);

        $response = $this->get('/api/authors/active');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['data']['authors']);
        $this->assertEquals('Active Author', $data['data']['authors'][0]['name']);
    }

    public function testMergeAuthors()
    {
        $source = Author::create(['name' => 'Source', 'slug' => 'source', 'status' => 'active']);
        $target = Author::create(['name' => 'Target', 'slug' => 'target', 'status' => 'active']);

        $response = $this->post('/api/authors/merge', [
            'source_author_id' => $source->id,
            'target_author_id' => $target->id
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull(Author::find($source->id));
    }

    public function testMergeValidatesRequiredIds()
    {
        $response = $this->post('/api/authors/merge', [
            'source_author_id' => 1
        ]);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCheckDeleteReturnsCanDeleteWhenNoPagesExist()
    {
        // Arrange: create an author with no pages
        $author = Author::create([
            'name' => 'Lonely Author',
            'slug' => 'lonely-author',
            'status' => 'active',
        ]);

        // Act
        $response = $this->get("/api/authors/{$author->id}/check-delete");

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
        $author = Author::create([
            'name' => 'Author With Pages',
            'slug' => 'author-with-pages',
            'status' => 'active',
        ]);

        // Create one or more pages for this author
        $page = \App\Models\Page::create([
            'title' => 'Test Page',
            'author_id' => $author->id,
            'slug' => 'test-page',
        ]);

        // Act
        $response = $this->get("/api/authors/{$author->id}/check-delete");

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
        $response = $this->get('/api/authors/9999/check-delete');

        // Assert
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Author not found', $data['data']['message']);
    }
}