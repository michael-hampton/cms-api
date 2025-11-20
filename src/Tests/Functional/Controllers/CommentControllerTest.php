<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Comment;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class CommentControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private $page;
    private $member;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test page
        $this->page = $this->createPage();

        $this->member = $this->createMember();
    }

    public function testStoreCommentSuccessfully()
    {
        $data = [
            'page_id' => $this->page->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'content' => 'This is a great article!'
        ];

        $response = $this->post('/comments', $data);

        $this->assertResponseStatus(200, $response);
        $this->assertJsonResponse($response);

        $json = json_decode($response->getContent(), true);

        $this->assertTrue($json['success']);
        $this->assertArrayHasKey('comment', $json);
        $this->assertEquals('John Doe', $json['comment']['name']);
        $this->assertContains($json['status'], ['approved', 'pending']);
    }

    public function testStoreCommentAsAuthenticatedMember()
    {
        // Authenticate the member
        $this->actingAsMember($this->member);

        $data = [
            'page_id' => $this->page->id,
            'member_id' => $this->member->id,
            'content' => 'This is a great article from an authenticated member!'
        ];

        $response = $this->post('/comments', $data);

        $this->assertResponseStatus(200, $response);
        $this->assertJsonResponse($response);

        $json = json_decode($response->getContent(), true);

        $this->assertTrue($json['success']);
        $this->assertArrayHasKey('comment', $json);
        $this->assertEquals($this->member->first_name . ' ' . $this->member->last_name, $json['comment']['name']);
        $this->assertEquals($this->member->email, $json['comment']['email']);
        $this->assertEquals($this->member->id, $json['comment']['member_id']);
        $this->assertEquals('approved', $json['status']); // Should be auto-approved for authenticated members
    }

    public function testStoreCommentAsAuthenticatedMemberDoesNotRequireNameEmail()
    {
        // Authenticate the member
        $this->actingAsMember($this->member);

        $data = [
            'page_id' => $this->page->id,
            'member_id' => $this->member->id,
            'content' => 'Comment without name/email fields'
            // name and email not provided
        ];

        $response = $this->post('/comments', $data);

        $this->assertResponseStatus(200, $response);

        $json = json_decode($response->getContent(), true);
        $this->assertTrue($json['success']);
        $this->assertEquals($this->member->first_name . ' ' . $this->member->last_name, $json['comment']['name']);
        $this->assertEquals($this->member->email, $json['comment']['email']);
    }

    public function testStoreCommentRequiresAuthenticationOrNameEmail()
    {
        $data = [
            'page_id' => $this->page->id,
            'content' => 'Comment without credentials'
            // Missing name, email, and member_id
        ];

        $response = $this->post('/comments', $data);

        $this->assertResponseStatus(422, $response);

        $json = json_decode($response->getContent(), true);

        $this->assertarrayHasKey('error', $json);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('name', $json['errors']);
        $this->assertArrayHasKey('email', $json['errors']);
    }

    public function testStoreCommentRequiresAuthentication()
    {
        $data = [
            'page_id' => $this->page->id,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'content' => 'Nice post!'
        ];

        // Test without auth if your system requires it
        $response = $this->post('/comments', $data);

        // Adjust based on your authentication requirements
        $this->assertResponseOk($response);
    }

    public function testStoreCommentValidatesRequiredFields()
    {
        $data = [
            'page_id' => $this->page->id,
            // Missing name, email, content
        ];

        $response = $this->post('/comments', $data);

        $this->assertResponseStatus(422, $response);

        $json = json_decode($response->getContent(), true);

        $this->assertarrayHasKey('error', $json);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('name', $json['errors']);
        $this->assertArrayHasKey('email', $json['errors']);
        $this->assertArrayHasKey('content', $json['errors']);
    }

    public function testStoreCommentSanitizesContent()
    {
        $data = [
            'page_id' => $this->page->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'content' => 'Hello <script>alert("xss")</script> world!'
        ];

        $response = $this->post('/comments', $data);

        $this->assertResponseOk($response);

        $json = json_decode($response->getContent(), true);
        $this->assertStringNotContainsString('<script>', $json['comment']['content']);
    }

    public function testStoreCommentDetectsSpam()
    {
        $data = [
            'page_id' => $this->page->id,
            'name' => 'Spammer',
            'email' => 'spam@example.com',
            'content' => 'Buy viagra now! Visit http://spam1.com http://spam2.com http://spam3.com http://spam4.com'
        ];

        $response = $this->post('/comments', $data);

        $this->assertResponseOk($response);

        $json = json_decode($response->getContent(), true);
        $this->assertEquals('spam', $json['status']);
    }

    public function testModerateCommentApproves()
    {
        // Create pending comment
        $comment = Comment::create([
            'page_id' => $this->page->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'content' => 'Test comment',
            'status' => 'pending',
            'site_id' => $this->siteId
        ]);

        $response = $this->put("/comments/{$comment->id}/moderate", [
            'status' => 'approved'
        ]);

        $this->assertResponseOk($response);

        $json = json_decode($response->getContent(), true);

        $this->assertTrue($json['success']);

        // Verify in database
        $updated = Comment::find($comment->id);
        $this->assertEquals('approved', $updated->status);
    }

    public function testModerateCommentRejects()
    {
        $comment = Comment::create([
            'page_id' => $this->page->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'content' => 'Test comment',
            'status' => 'pending',
            'site_id' => $this->siteId
        ]);

        $response = $this->put("/comments/{$comment->id}/moderate", [
            'status' => 'rejected'
        ]);

        $this->assertResponseOk($response);

        $updated = Comment::find($comment->id);
        $this->assertEquals('rejected', $updated->status);
    }

    public function testModerateCommentValidatesStatus()
    {
        $comment = Comment::create([
            'page_id' => $this->page->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'content' => 'Test comment',
            'status' => 'pending',
            'site_id' => $this->siteId
        ]);

        $response = $this->put("/comments/{$comment->id}/moderate", [
            'status' => 'invalid_status'
        ]);

        $this->assertResponseStatus(422, $response);
    }

    public function testModerateCommentNotFound()
    {
        $response = $this->put("/comments/99999/moderate", [
            'status' => 'approved'
        ]);

        $this->assertResponseStatus(404, $response);
    }

    public function testGetCommentsForPage()
    {
        // Create multiple comments
        Comment::create([
            'page_id' => $this->page->id,
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'content' => 'Comment 1',
            'status' => 'approved',
            'site_id' => $this->siteId
        ]);

        Comment::create([
            'page_id' => $this->page->id,
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'content' => 'Comment 2',
            'status' => 'approved',
            'site_id' => $this->siteId
        ]);

        Comment::create([
            'page_id' => $this->page->id,
            'name' => 'User 3',
            'email' => 'user3@example.com',
            'content' => 'Comment 3',
            'status' => 'pending',
            'site_id' => $this->siteId
        ]);

        $response = $this->get("/pages/{$this->page->id}/comments");

        $this->assertResponseOk($response);

        $json = json_decode($response->getContent(), true);
        $this->assertTrue($json['success']);
        $this->assertCount(2, $json['comments']); // Only approved
        $this->assertEquals(3, $json['stats']['total']);
        $this->assertEquals(2, $json['stats']['approved']);
        $this->assertEquals(1, $json['stats']['pending']);
    }

    public function testDeleteComment()
    {
        $comment = Comment::create([
            'page_id' => $this->page->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'content' => 'Test comment',
            'status' => 'approved',
            'site_id' => $this->siteId
        ]);

        $response = $this->delete("/comments/{$comment->id}");

        $this->assertResponseOk($response);

        $json = json_decode($response->getContent(), true);
        $this->assertTrue($json['success']);

        // Verify deleted
        $this->assertNull(Comment::find($comment->id));
    }

    public function testAutoApprovalForTrustedUsers()
    {
        // Create first approved comment
        Comment::create([
            'page_id' => $this->page->id,
            'name' => 'Trusted User',
            'email' => 'trusted@example.com',
            'content' => 'First comment',
            'status' => 'approved',
            'site_id' => $this->siteId
        ]);

        // Second comment should be auto-approved
        $data = [
            'page_id' => $this->page->id,
            'name' => 'Trusted User',
            'email' => 'trusted@example.com',
            'content' => 'Second comment'
        ];

        $response = $this->post('/comments', $data);

        $json = json_decode($response->getContent(), true);
        $this->assertEquals('approved', $json['status']);
    }

    public function testAuthenticatedMemberCommentsAutoApproved()
    {
        $this->actingAsMember($this->member);

        $data = [
            'page_id' => $this->page->id,
            'member_id' => $this->member->id,
            'content' => 'This should be auto-approved'
        ];

        $response = $this->post('/comments', $data);

        $json = json_decode($response->getContent(), true);
        $this->assertEquals('approved', $json['status']);

        // Verify in database
        $comment = Comment::where('page_id', $this->page->id)
            ->where('member_id', $this->member->id)
            ->first();

        $this->assertNotNull($comment);
        $this->assertEquals('approved', $comment->status);
    }

    public function testMemberCommentIncludesRelationship()
    {
        $this->actingAsMember($this->member);

        $data = [
            'page_id' => $this->page->id,
            'member_id' => $this->member->id,
            'content' => 'Comment with member relationship'
        ];

        $response = $this->post('/comments', $data);
        $json = json_decode($response->getContent(), true);

        $comment = Comment::find($json['comment']['id']);
        $this->assertNotNull($comment->member);
        $this->assertEquals($this->member->id, $comment->member->id);
    }
}