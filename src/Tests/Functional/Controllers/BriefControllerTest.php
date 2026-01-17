<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Brief;
use App\Models\BriefAttachment;
use App\Models\BriefComment;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class BriefControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsBriefsList()
    {
        $user = $this->createUser();
        $this->createBrief(['owner_id' => $user->id]);

        $response = $this->getForSite('/api/briefs');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('items', $data);
        $this->assertCount(1, $data['items']);
    }

    public function testIndexWithSearchCriteria()
    {
        $user = $this->createUser();
        $this->createBrief(['status' => 'active', 'owner_id' => $user->id]);
        $this->createBrief(['status' => 'converted', 'owner_id' => $user->id]);

        $response = $this->getForSite('/api/briefs?status=active');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('active', $data['items'][0]['status']);
    }

    public function testStoreCreatesNewBrief()
    {
        $user = $this->createUser();

        $briefData = [
            'title' => 'New Brief',
            'description' => 'Test description',
            'owner_id' => $user->id,
            'site_id' => $this->siteId
        ];

        $response = $this->postForSite('/api/briefs', $briefData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('New Brief', $data['data']['title']);
        $this->assertEquals('Test description', $data['data']['description']);
    }

    public function testStoreWithCategory()
    {
        $user = $this->createUser();
        $category = $this->createCategory(['name' => 'Tech']);

        $briefData = [
            'title' => 'Tech Brief',
            'owner_id' => $user->id,
            'category_id' => $category->id,
            'site_id' => $this->siteId
        ];

        $response = $this->postForSite('/api/briefs', $briefData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals($category->id, $data['data']['category_id']);
    }

    public function testShowReturnsBriefById()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id, 'title' => 'Test Brief']);

        $response = $this->getForSite("/api/briefs/{$brief->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Test Brief', $data['data']['title']);
    }

    public function testShowReturns404ForNonexistent()
    {
        $response = $this->getForSite('/api/briefs/999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateModifiesExistingBrief()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id, 'title' => 'Original']);

        $updateData = [
            'title' => 'Updated Title',
            'description' => 'Updated description'
        ];

        $response = $this->putForSite("/api/briefs/{$brief->id}", $updateData);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Updated Title', $data['data']['title']);
        $this->assertEquals('Updated description', $data['data']['description']);
    }

    public function testDestroyDeletesBrief()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $response = $this->deleteForSite("/api/briefs/{$brief->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull(Brief::find($brief->id));
    }

    public function testDestroyReturns404ForNonexistent()
    {
        $response = $this->deleteForSite('/api/briefs/999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAddAttachmentCreatesImageAttachment()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);
        $image = $this->createImage();

        $attachmentData = [
            'type' => 'image',
            'image_id' => $image->id,
            'file_url' => 'http://example.com/image.jpg',
            'file_name' => 'image.jpg',
            'metadata' => [
                'alt_text' => 'Test image',
                'credit' => 'Test credit'
            ],
            'sort_order' => 0
        ];

        $response = $this->postForSite("/api/briefs/{$brief->id}/attachments", $attachmentData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('image', $data['data']['type']);
        $this->assertEquals('image.jpg', $data['data']['file_name']);
    }

    public function testAddAttachmentCreatesProductAttachment()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $attachmentData = [
            'type' => 'product',
            'url' => 'http://example.com/product',
            'metadata' => [
                'product_name' => 'Test Product',
                'product_price' => '$99.99'
            ],
            'sort_order' => 0
        ];

        $response = $this->postForSite("/api/briefs/{$brief->id}/attachments", $attachmentData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('product', $data['data']['type']);
        $this->assertEquals('http://example.com/product', $data['data']['url']);
    }

    public function testDeleteAttachmentRemovesAttachment()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);
        $attachment = $this->createBriefAttachment($brief->id);

        $response = $this->deleteForSite("/api/briefs/{$brief->id}/attachments/{$attachment->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull(BriefAttachment::find($attachment->id));
    }

    public function testDeleteAttachmentReturns404ForNonexistent()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $response = $this->deleteForSite("/api/briefs/{$brief->id}/attachments/999");

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAddCommentCreatesComment()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $commentData = [
            'content' => 'Test comment',
            'user_id' => $user->id
        ];

        $response = $this->postForSite("/api/briefs/{$brief->id}/comments", $commentData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Test comment', $data['data']['content']);
    }

    public function testAddCommentWithHighlightedText()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $commentData = [
            'content' => 'Comment on highlighted text',
            'user_id' => $user->id,
            'highlighted_text' => 'Selected text',
            'highlighted_range' => ['start' => 0, 'end' => 13]
        ];

        $response = $this->postForSite("/api/briefs/{$brief->id}/comments", $commentData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Selected text', $data['data']['highlighted_text']);
    }

    public function testAddCommentAsReply()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);
        $parentComment = $this->createBriefComment($brief->id, $user->id, [
            'content' => 'Parent comment'
        ]);

        $replyData = [
            'content' => 'Reply to comment',
            'user_id' => $user->id,
            'parent_comment_id' => $parentComment->id
        ];

        $response = $this->postForSite("/api/briefs/{$brief->id}/comments", $replyData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals($parentComment->id, $data['data']['parent_comment_id']);
    }

    public function testDeleteCommentRemovesComment()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);
        $comment = $this->createBriefComment($brief->id, $user->id);

        $response = $this->deleteForSite("/api/briefs/{$brief->id}/comments/{$comment->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull(BriefComment::find($comment->id));
    }

    public function testDeleteCommentReturns404ForNonexistent()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $response = $this->deleteForSite("/api/briefs/{$brief->id}/comments/999");

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testConvertToPageCreatesPage()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id, 'title' => 'Brief to Convert']);

        $conversionData = [
            'title' => 'Brief to Convert',
            'owner_id' => $user->id,
            'images' => [],
            'products' => []
        ];

        $response = $this->postForSite("/api/briefs/{$brief->id}/convert", $conversionData);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['data']['success']);
        $this->assertArrayHasKey('page_id', $data['data']);
        $this->assertArrayHasKey('brief_id', $data['data']);
    }

    public function testConvertToPageWithImages()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);
        $image = $this->createImage();
        $attachment = $this->createBriefAttachment($brief->id, [
            'type' => 'image',
            'image_id' => $image->id,
            'file_url' => $image->file_path,
            'metadata' => ['alt_text' => 'Test alt']
        ]);

        $conversionData = [
            'title' => 'Brief with Images',
            'owner_id' => $user->id,
            'images' => [
                [
                    'attachment_id' => $attachment->id,
                    'image_id' => $image->id,
                    'alt_text' => 'Test alt',
                    'credit' => 'Test credit',
                    'caption' => 'Test caption'
                ]
            ],
            'products' => []
        ];

        $response = $this->postForSite("/api/briefs/{$brief->id}/convert", $conversionData);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['data']['success']);

        // Verify page was created with image block
        $pageId = $data['data']['page_id'];
        $pageResponse = $this->getForSite("/api/pages/{$pageId}");
        $pageData = json_decode($pageResponse->getContent(), true);

        $this->assertNotEmpty($pageData['data']['blocks']);
        $this->assertEquals('image', $pageData['data']['blocks'][0]['type']);
    }

    public function testConvertToPageWithProducts()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);
        $product = $this->createProduct();
        $attachment = $this->createBriefAttachment($brief->id, [
            'type' => 'product',
            'product_id' => $product->id,
            'url' => 'http://example.com/product',
            'metadata' => [
                'product_name' => 'Test Product',
                'product_price' => '$99.99'
            ]
        ]);

        $conversionData = [
            'title' => 'Brief with Products',
            'owner_id' => $user->id,
            'images' => [],
            'products' => [
                [
                    'attachment_id' => $attachment->id,
                    'product_id' => $product->id,
                    'url' => 'http://example.com/product',
                    'conversion_type' => 'product'
                ]
            ]
        ];

        $response = $this->postForSite("/api/briefs/{$brief->id}/convert", $conversionData);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['data']['success']);

        // Verify page was created with product block
        $pageId = $data['data']['page_id'];
        $pageResponse = $this->getForSite("/api/pages/{$pageId}");
        $pageData = json_decode($pageResponse->getContent(), true);

        $this->assertNotEmpty($pageData['data']['blocks']);
        $this->assertEquals('product', $pageData['data']['blocks'][0]['type']);
    }

    public function testConvertToPageWithDealBlocks()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);
        $attachment = $this->createBriefAttachment($brief->id, [
            'type' => 'product',
            'url' => 'http://example.com/product',
            'metadata' => ['product_name' => 'Deal Product', 'product_price' => '$99.99']
        ]);

        $conversionData = [
            'title' => 'Brief with Deal',
            'owner_id' => $user->id,
            'images' => [],
            'products' => [
                [
                    'attachment_id' => $attachment->id,
                    'url' => 'http://example.com/product',
                    'conversion_type' => 'deal'
                ]
            ]
        ];

        $response = $this->postForSite("/api/briefs/{$brief->id}/convert", $conversionData);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['data']['success']);

        $pageId = $data['data']['page_id'];
        $pageResponse = $this->getForSite("/api/pages/{$pageId}");
        $pageData = json_decode($pageResponse->getContent(), true);

        $this->assertNotEmpty($pageData['data']['blocks']);
        $this->assertEquals('deal', $pageData['data']['blocks'][0]['type']);
    }

    public function testConvertToPageMarksBriefAsConverted()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id, 'status' => 'active']);

        $conversionData = [
            'title' => 'Brief to Convert',
            'owner_id' => $user->id,
            'images' => [],
            'products' => []
        ];

        $response = $this->postForSite("/api/briefs/{$brief->id}/convert", $conversionData);

        $this->assertEquals(200, $response->getStatusCode());

        // Verify brief status changed
        $updatedBrief = Brief::find($brief->id);
        $this->assertEquals('converted', $updatedBrief->status);
        $this->assertNotNull($updatedBrief->converted_page_id);
        $this->assertNotNull($updatedBrief->converted_at);
    }

    public function testConvertToPageReturns404ForNonexistentBrief()
    {
        $response = $this->postForSite('/api/briefs/999/convert', []);

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testArchiveBriefSuccessfully()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id, 'status' => 'active']);

        $response = $this->postForSite("/api/briefs/{$brief->id}/archive");

        $this->assertEquals(200, $response->getStatusCode());

        // Verify status changed
        $updatedBrief = Brief::find($brief->id);
        $this->assertEquals('archived', $updatedBrief->status);
    }

    public function testArchiveBriefReturns404ForNonexistent()
    {
        $response = $this->postForSite('/api/briefs/999/archive');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testIndexFiltersByOwner()
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();

        $this->createBrief(['owner_id' => $user1->id, 'title' => 'User 1 Brief']);
        $this->createBrief(['owner_id' => $user2->id, 'title' => 'User 2 Brief']);

        $response = $this->getForSite("/api/briefs?owner_id={$user1->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('User 1 Brief', $data['items'][0]['title']);
    }

    public function testIndexFiltersByCategory()
    {
        $user = $this->createUser();
        $category1 = $this->createCategory();
        $category2 = $this->createCategory();

        $this->createBrief(['owner_id' => $user->id, 'category_id' => $category1->id]);
        $this->createBrief(['owner_id' => $user->id, 'category_id' => $category2->id]);

        $response = $this->getForSite("/api/briefs?category_id={$category1->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['items']);
    }

    public function testIndexSortsByCreatedAtDesc()
    {
        $user = $this->createUser();
        $oldest = $this->createBrief([
            'owner_id' => $user->id,
            'title' => 'Oldest',
            'created_at' => '2024-01-01 00:00:00'
        ]);
        $newest = $this->createBrief([
            'owner_id' => $user->id,
            'title' => 'Newest',
            'created_at' => '2024-12-31 23:59:59'
        ]);

        $response = $this->getForSite('/api/briefs');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Newest', $data['items'][0]['title']);
    }

    public function testShowLoadsAllRelationships()
    {
        $user = $this->createUser();
        $category = $this->createCategory();
        $brief = $this->createBrief([
            'owner_id' => $user->id,
            'category_id' => $category->id
        ]);
        $this->createBriefAttachment($brief->id);
        $this->createBriefComment($brief->id, $user->id);

        $response = $this->getForSite("/api/briefs/{$brief->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('attachments', $data['data']);
        $this->assertArrayHasKey('comments', $data['data']);
        $this->assertArrayHasKey('owner', $data['data']);
        $this->assertArrayHasKey('category', $data['data']);
    }

    public function testConvertToPageSetsCategory()
    {
        $user = $this->createUser();
        $category = $this->createCategory();
        $brief = $this->createBrief(['owner_id' => $user->id]);
        $conversionData = [
            'title' => 'Brief with Category',
            'owner_id' => $user->id,
            'category_id' => $category->id,
            'images' => [],
            'products' => []
        ];

        $response = $this->postForSite("/api/briefs/{$brief->id}/convert", $conversionData);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $pageId = $data['data']['page_id'];
        $pageResponse = $this->getForSite("/api/pages/{$pageId}");
        $pageData = json_decode($pageResponse->getContent(), true);

        $this->assertNotEmpty($pageData['data']['categories']);

        // Check that our specific category is in the array
        $categoryIds = array_column($pageData['data']['categories'], 'id');
        $this->assertContains($category->id, $categoryIds,
            'The specified category should be assigned to the page');
    }
}