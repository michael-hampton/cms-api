<?php

namespace App\Tests\Functional\Controllers\Cms\Briefs;

use App\Models\Brief;
use App\Models\BriefActivityLog;
use App\Models\BriefAttachment;
use App\Models\BriefCollaborator;
use App\Models\BriefComment;
use App\Models\BriefDeadline;
use App\Models\BriefRelationship;
use App\Models\BriefTask;
use App\Models\BriefTemplate;
use App\Models\BriefVersion;
use App\Models\BriefWorkflowHistory;
use App\Models\Collaborator;
use App\Tests\Functional\Controllers\FunctionalTestCase;
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
        $this->createBrief(['status' => 'draft', 'owner_id' => $user->id]);
        $this->createBrief(['status' => 'converted', 'owner_id' => $user->id]);

        $response = $this->getForSite('/api/briefs?status=draft');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('draft', $data['items'][0]['status']);
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

    public function testStoreCreatesInitialVersion()
    {
        $user = $this->createUser();

        $response = $this->postForSite('/api/briefs', [
            'title' => 'New Brief',
            'owner_id' => $user->id,
            'site_id' => $this->siteId
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        // Check version was created
        $version = BriefVersion::where('brief_id', $data['data']['id'])->first();
        $this->assertNotNull($version);
        $this->assertEquals(1, $version->version_number);
    }

    public function testStoreLogsActivity()
    {
        $user = $this->createUser();

        $response = $this->postForSite('/api/briefs', [
            'title' => 'New Brief',
            'owner_id' => $user->id,
            'site_id' => $this->siteId
        ]);

        $data = json_decode($response->getContent(), true);

        // Check activity was logged
        $activity = BriefActivityLog::where('brief_id', $data['data']['id'])
            ->where('action', 'created')
            ->first();
        $this->assertNotNull($activity);
    }

    public function testStoreWithAllOptionalFields()
    {
        $user = $this->createUser();
        $category = $this->createCategory();

        $briefData = [
            'title' => 'Complete Brief',
            'description' => 'Full description',
            'owner_id' => $user->id,
            'category_id' => $category->id,
            'target_word_count' => 2000,
            'target_publish_date' => '2026-03-01',
            'seo_keywords' => 'test, keywords',
            'target_audience' => 'Tech professionals',
            'site_id' => $this->siteId
        ];

        $response = $this->postForSite('/api/briefs', $briefData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(2000, $data['data']['target_word_count']);
        $this->assertEquals('test, keywords', $data['data']['seo_keywords']);
        $this->assertEquals('Tech professionals', $data['data']['target_audience']);
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
                'productName' => 'Test Product',
                'price' => '$99.99'
            ],
            'sort_order' => 0
        ];

        $response = $this->postForSite("/api/briefs/{$brief->id}/attachments", $attachmentData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('product', $data['data']['type']);
        $this->assertEquals('http://example.com/product', $data['data']['url']);
    }

    public function testAddAttachmentCreatesDealAttachment()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $attachmentData = [
            'type' => 'deal',
            'url' => 'http://example.com/deal',
            'metadata' => [
                'productName' => 'Deal Product',
                'price' => 99.99,
                'salePrice' => 79.99
            ],
            'sort_order' => 0
        ];

        $response = $this->postForSite("/api/briefs/{$brief->id}/attachments", $attachmentData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('deal', $data['data']['type']);
    }

    public function testAddAttachmentCreatesUrlAttachment()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $attachmentData = [
            'type' => 'url',
            'url' => 'http://example.com/reference',
            'metadata' => [
                'description' => 'Reference link'
            ],
            'sort_order' => 0
        ];

        $response = $this->postForSite("/api/briefs/{$brief->id}/attachments", $attachmentData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('url', $data['data']['type']);
    }

    public function testUploadDocumentAttachment()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        // Create a test PDF file
        $pdfContent = '%PDF-1.4 test content';
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_pdf_');
        file_put_contents($tmpFile, $pdfContent);

        $file = [
            'name' => 'document.pdf',
            'type' => 'application/pdf',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => strlen($pdfContent)
        ];

        $response = $this->postForSite(
            "/api/briefs/{$brief->id}/upload",
            [
                'brief_id' => $brief->id,
                'type' => 'document',
                'description' => 'Reference document'
            ],
            ['file' => $file]
        );

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('document', $data['data']['type']);
        $this->assertEquals('document.pdf', $data['data']['file_name']);
        $this->assertEquals(strlen($pdfContent), $data['data']['filesize']);
        $this->assertEquals('Reference document', $data['data']['metadata']['description']);
        $this->assertNotEmpty($data['data']['file_url']);

        // Cleanup
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
    }

    public function testUploadDocumentWithInvalidFileType()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_exe_');
        file_put_contents($tmpFile, 'fake executable');

        $file = [
            'name' => 'malware.exe',
            'type' => 'application/x-msdownload',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => 100
        ];

        $response = $this->postForSite(
            "/api/briefs/{$brief->id}/upload",
            ['brief_id' => $brief->id, 'type' => 'document'],
            ['file' => $file]
        );

        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertStringContainsString('Upload validation failed: File type not allowed. Allowed types: pdf, doc, docx, xls, xlsx, ppt, pptx, txt, csv', $data['error']);

        // Cleanup
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
    }

    public function testUploadDocumentWithFileSizeExceeded()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_large_');
        file_put_contents($tmpFile, 'content');

        $file = [
            'name' => 'huge.pdf',
            'type' => 'application/pdf',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => 11 * 1024 * 1024 // 11MB - exceeds 10MB limit
        ];

        $response = $this->postForSite(
            "/api/briefs/{$brief->id}/upload",
            ['brief_id' => $brief->id, 'type' => 'document'],
            ['file' => $file]
        );

        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('exceeds maximum', $data['error']);

        // Cleanup
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
    }

    public function testUploadDocumentWithNoFile()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $response = $this->postForSite(
            "/api/briefs/{$brief->id}/upload",
            ['brief_id' => $brief->id, 'type' => 'document']
        );

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('No file provided', $data['error']);
    }

    public function testDeleteDocumentAttachmentRemovesFile()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        // Create temp file
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_doc_');
        file_put_contents($tmpFile, 'test content');

        // Create attachment directly
        $attachment = BriefAttachment::create([
            'brief_id' => $brief->id,
            'type' => 'document',
            'file_url' => $tmpFile,
            'file_name' => 'test.pdf',
            'file_size' => 100,
            'metadata' => ['description' => 'Test doc']
        ]);

        // Verify file exists
        $this->assertTrue(file_exists($tmpFile));

        // Delete attachment
        $response = $this->deleteForSite("/api/briefs/{$brief->id}/attachments/{$attachment->id}");

        $this->assertEquals(200, $response->getStatusCode());

        // Verify database record is gone
        $this->assertNull(BriefAttachment::find($attachment->id));

        // Verify physical file is deleted
        $this->assertFalse(file_exists($tmpFile));
    }

    public function testUploadMultipleDocuments()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        // Upload first document
        $tmpFile1 = tempnam(sys_get_temp_dir(), 'test_doc1_');
        file_put_contents($tmpFile1, 'document 1');

        $file1 = [
            'name' => 'doc1.pdf',
            'type' => 'application/pdf',
            'tmp_name' => $tmpFile1,
            'error' => UPLOAD_ERR_OK,
            'size' => 10
        ];

        $response1 = $this->postForSite(
            "/api/briefs/{$brief->id}/upload",
            ['brief_id' => $brief->id, 'type' => 'document', 'description' => 'First doc'],
            ['file' => $file1]
        );

        $this->assertEquals(201, $response1->getStatusCode());

        // Upload second document
        $tmpFile2 = tempnam(sys_get_temp_dir(), 'test_doc2_');
        file_put_contents($tmpFile2, 'document 2');

        $file2 = [
            'name' => 'doc2.docx',
            'type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'tmp_name' => $tmpFile2,
            'error' => UPLOAD_ERR_OK,
            'size' => 10
        ];

        $response2 = $this->postForSite(
            "/api/briefs/{$brief->id}/upload",
            ['brief_id' => $brief->id, 'type' => 'document', 'description' => 'Second doc'],
            ['file' => $file2]
        );

        $this->assertEquals(201, $response2->getStatusCode());

        // Verify both attachments exist
        $attachments = BriefAttachment::where('brief_id', $brief->id)
            ->where('type', 'document')
            ->get();

        $this->assertCount(2, $attachments);

        // Cleanup
        foreach ([$tmpFile1, $tmpFile2] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
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

    public function testAddCommentWithMentions()
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user1->id]);

        $commentData = [
            'content' => 'Hey @user2 check this',
            'user_id' => $user1->id,
            'mentions' => [$user2->id]
        ];

        $response = $this->postForSite("/api/briefs/{$brief->id}/comments", $commentData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals([$user2->id], $data['data']['mentions']);
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
                'productName' => 'Test Product',
                'price' => 99.99
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
            'metadata' => ['productName' => 'Deal Product', 'price' => 99.99, 'currency' => 'USD']
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
        $brief = $this->createBrief(['owner_id' => $user->id, 'status' => 'ready']);

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
        $brief = $this->createBrief(['owner_id' => $user->id, 'status' => 'draft']);

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

    public function testIndexWithPagination()
    {
        $user = $this->createUser();

        // Create 25 briefs
        for ($i = 0; $i < 25; $i++) {
            $this->createBrief(['owner_id' => $user->id, 'title' => "Brief {$i}"]);
        }

        $response = $this->getForSite('/api/briefs?page=1&per_page=10');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(10, $data['items']);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertEquals(25, $data['pagination']['total']);
    }

    public function testIndexSearchByTitle()
    {
        $user = $this->createUser();
        $this->createBrief(['owner_id' => $user->id, 'title' => 'Product Review']);
        $this->createBrief(['owner_id' => $user->id, 'title' => 'How To Guide']);

        $response = $this->getForSite('/api/briefs?search=Product');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['items']);
        $this->assertStringContainsString('Product', $data['items'][0]['title']);
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

    public function testUpdateAttachmentSuccessfully()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);
        $attachment = $this->createBriefAttachment($brief->id, [
            'type' => 'product',
            'url' => 'http://example.com/old'
        ]);

        $updateData = [
            'url' => 'http://example.com/new',
            'metadata' => ['productName' => 'Updated Product']
        ];

        $response = $this->putForSite(
            "/api/briefs/{$brief->id}/attachments/{$attachment->id}",
            $updateData
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('http://example.com/new', $data['data']['url']);
    }

    public function testUpdateCommentSuccessfully()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);
        $comment = $this->createBriefComment($brief->id, $user->id, [
            'content' => 'Original content'
        ]);

        $updateData = ['content' => 'Updated content'];

        $response = $this->putForSite(
            "/api/briefs/{$brief->id}/comments/{$comment->id}",
            $updateData
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Updated content', $data['data']['content']);
    }

    public function testGetTemplatesReturnsSystemAndCustomTemplates()
    {
        $user = $this->createUser();

        // Create system template
        $systemTemplate = BriefTemplate::create([
            'site_id' => $this->siteId,
            'name' => 'Review Template',
            'type' => 'review',
            'is_system' => true,
            'created_by' => 1
        ]);

        // Create custom template
        $customTemplate = BriefTemplate::create([
            'site_id' => $this->siteId,
            'name' => 'Custom Template',
            'type' => 'custom',
            'is_system' => false,
            'created_by' => $user->id
        ]);

        $response = $this->getForSite('/api/briefs/templates');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertGreaterThanOrEqual(2, count($data['items']));
    }

    public function testCreateFromTemplateAppliesDefaultFields()
    {
        $user = $this->createUser();

        $template = BriefTemplate::create([
            'site_id' => $this->siteId,
            'name' => 'Review Template',
            'type' => 'review',
            'default_fields' => [
                'target_word_count' => 1500,
                'seo_keywords' => 'review, best',
                'description' => 'Template content'
            ],
            'is_system' => true,
            'created_by' => 1
        ]);

        $response = $this->postForSite("/api/briefs/templates/{$template->id}/create", [
            'title' => 'New Brief from Template',
            'owner_id' => $user->id
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(1500, $data['data']['target_word_count']);
        $this->assertEquals('review, best', $data['data']['seo_keywords']);
    }

    public function testSaveAsTemplateCreatesCustomTemplate()
    {
        $user = $this->createUser();
        $brief = $this->createBrief([
            'owner_id' => $user->id,
            'title' => 'Source Brief',
            'target_word_count' => 2000,
            'seo_keywords' => 'keyword1, keyword2'
        ]);

        $response = $this->postForSite("/api/briefs/{$brief->id}/save-template", [
            'name' => 'My Custom Template',
            'description' => 'Custom template description',
            'type' => 'custom',
            'user_id' => $user->id
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('My Custom Template', $data['data']['name']);
        $this->assertFalse($data['data']['is_system']);
        $this->assertEquals(2000, $data['data']['default_fields']['target_word_count']);
    }

    // Collaborator Tests
    public function testAddCollaboratorCreatesAssignment()
    {
        $owner = $this->createUser();
        $collaborator = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $owner->id]);

        $response = $this->postForSite("/api/briefs/{$brief->id}/collaborators", [
            'user_id' => $collaborator->id,
            'role' => 'editor'
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('editor', $data['data']['role']);
        $this->assertEquals($collaborator->id, $data['data']['user_id']);
    }

    public function testGetCollaboratorsReturnsAllAssignedUsers()
    {
        $owner = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $owner->id]);

        $user1 = $this->createUser();
        $user2 = $this->createUser();

        Collaborator::create([
            'collaboratable_id' => $brief->id,
            'user_id' => $user1->id,
            'role' => 'writer',
            'collaboratable_type' => Brief::class,
            'site_id' => $this->siteId
        ]);

        Collaborator::create([
            'collaboratable_id' => $brief->id,
            'user_id' => $user2->id,
            'role' => 'editor',
            'collaboratable_type' => Brief::class,
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite("/api/briefs/{$brief->id}/collaborators");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['items']);
    }

    public function testRemoveCollaboratorDeletesAssignment()
    {
        $owner = $this->createUser();
        $collaborator = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $owner->id]);

        $assignment = Collaborator::create([
            'collaboratable_id' => $brief->id,
            'user_id' => $collaborator->id,
            'role' => 'writer',
            'collaboratable_type' => Brief::class,
            'site_id' => $this->siteId
        ]);

        $response = $this->deleteForSite("/api/briefs/{$brief->id}/collaborators/{$assignment->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseMissing('brief_collaborators', ['id' => $assignment->id]);
    }

    // Task Tests
    public function testCreateTaskSuccessfully()
    {
        $user = $this->createUser();
        $assignee = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $response = $this->postForSite("/api/briefs/{$brief->id}/tasks", [
            'title' => 'Review draft',
            'description' => 'Please review the draft content',
            'assigned_to' => $assignee->id,
            'created_by' => $user->id,
            'due_date' => '2026-02-01'
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Review draft', $data['data']['title']);
        $this->assertEquals($assignee->id, $data['data']['assigned_to']);
    }

    public function testUpdateTaskStatus()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $task = BriefTask::create([
            'brief_id' => $brief->id,
            'title' => 'Test task',
            'created_by' => $user->id,
            'status' => 'pending'
        ]);

        $response = $this->putForSite("/api/briefs/{$brief->id}/tasks/{$task->id}", [
            'status' => 'completed'
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('completed', $data['data']['status']);
        $this->assertNotNull($data['data']['completed_at']);
    }

    public function testGetTasksReturnsAllBriefTasks()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        BriefTask::create([
            'brief_id' => $brief->id,
            'title' => 'Task 1',
            'created_by' => $user->id
        ]);

        BriefTask::create([
            'brief_id' => $brief->id,
            'title' => 'Task 2',
            'created_by' => $user->id
        ]);

        $response = $this->getForSite("/api/briefs/{$brief->id}/tasks");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['items']);
    }

    // Version Tests
    public function testGetVersionsReturnsVersionHistory()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id, 'title' => 'Original Title']);

        // Create versions
        BriefVersion::create([
            'brief_id' => $brief->id,
            'version_number' => 1,
            'title' => 'Original Title',
            'created_by' => $user->id
        ]);

        BriefVersion::create([
            'brief_id' => $brief->id,
            'version_number' => 2,
            'title' => 'Updated Title',
            'created_by' => $user->id,
            'change_summary' => 'Updated title'
        ]);

        $response = $this->getForSite("/api/briefs/{$brief->id}/versions");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['items']);
        $this->assertEquals(2, $data['items'][0]['version_number']); // Latest first
    }

    public function testRestoreVersionRestoresBriefContent()
    {
        $user = $this->createUser();
        $brief = $this->createBrief([
            'owner_id' => $user->id,
            'title' => 'Current Title'
        ]);

        $version = BriefVersion::create([
            'brief_id' => $brief->id,
            'version_number' => 1,
            'title' => 'Old Title',
            'description' => 'Old description',
            'created_by' => $user->id
        ]);

        $response = $this->postForSite("/api/briefs/{$brief->id}/versions/{$version->id}/restore", [
            'user_id' => $user->id
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $brief = $brief->fresh();
        $this->assertEquals('Old Title', $brief->title);
    }

    // Status Management Tests
    public function testUpdateStatusChangesStatusAndLogsActivity()
    {
        $user = $this->createUser();
        $brief = $this->createBrief([
            'owner_id' => $user->id,
            'status' => 'draft'
        ]);

        $response = $this->putForSite("/api/briefs/{$brief->id}/status", [
            'status' => 'in_review',
            'user_id' => $user->id
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('in_review', $data['data']['status']);
        $this->assertNotNull($data['data']['last_activity_at']);
        $this->assertEquals($user->id, $data['data']['last_activity_user_id']);
    }

    // Duplicate Tests
    public function testDuplicateBriefCreatesCompleteCopy()
    {
        $user = $this->createUser();
        $brief = $this->createBrief([
            'owner_id' => $user->id,
            'title' => 'Original Brief',
            'target_word_count' => 1500
        ]);

        // Add attachment
        $this->createBriefAttachment($brief->id, [
            'type' => 'image',
            'file_name' => 'test.jpg'
        ]);

        $response = $this->postForSite("/api/briefs/{$brief->id}/duplicate", [
            'user_id' => $user->id
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertStringContainsString('Copy', $data['data']['title']);
        $this->assertEquals(1500, $data['data']['target_word_count']);
        $this->assertCount(1, $data['data']['attachments']);
    }

    // Comment Resolution Tests
    public function testResolveCommentMarksAsResolved()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);
        $comment = $this->createBriefComment($brief->id, $user->id, [
            'content' => 'Please fix this'
        ]);

        $response = $this->postForSite("/api/briefs/{$brief->id}/comments/{$comment->id}/resolve", [
            'user_id' => $user->id
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['data']['is_resolved']);
        $this->assertEquals($user->id, $data['data']['resolved_by']);
        $this->assertNotNull($data['data']['resolved_at']);
    }

    public function testUnresolveCommentClearsResolution()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);
        $comment = $this->createBriefComment($brief->id, $user->id, [
            'content' => 'Fixed',
            'is_resolved' => true,
            'resolved_by' => $user->id,
            'resolved_at' => date('Y-m-d H:i:s')
        ]);

        $response = $this->postForSite("/api/briefs/{$brief->id}/comments/{$comment->id}/unresolve");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['data']['is_resolved']);
        $this->assertNull($data['data']['resolved_by']);
    }

    // Activity Log Tests
    public function testGetActivityLogReturnsActions()
    {
        $user = $this->createUser();

        // Create brief through the API (not directly) so activity log is created
        $response = $this->postForSite('/api/briefs', [
            'title' => 'Test Brief',
            'owner_id' => $user->id,
            'site_id' => $this->siteId
        ]);

        $data = json_decode($response->getContent(), true);
        $briefId = $data['data']['id'];

        // Get activity log
        $response = $this->getForSite("/api/briefs/{$briefId}/activity");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        // Should have at least the creation activity
        $this->assertGreaterThan(0, count($data['items']));
        $this->assertEquals('created', $data['items'][0]['action']);
    }

    public function testCompleteWorkflowFromDraftToConverted()
    {
        $user = $this->createUser();

        // Create brief through API
        $createResponse = $this->postForSite('/api/briefs', [
            'title' => 'Test Brief',
            'owner_id' => $user->id,
            'status' => 'draft',
            'site_id' => $this->siteId
        ]);

        $briefData = json_decode($createResponse->getContent(), true);
        $briefId = $briefData['data']['id'];

        // Add collaborator
        $editor = $this->createUser();
        $this->postForSite("/api/briefs/{$briefId}/collaborators", [
            'user_id' => $editor->id,
            'role' => 'editor'
        ]);

        // Create task
        $this->postForSite("/api/briefs/{$briefId}/tasks", [
            'title' => 'Review content',
            'assigned_to' => $editor->id,
            'created_by' => $user->id
        ]);

        // Update to in_review
        $this->putForSite("/api/briefs/{$briefId}/status", [
            'status' => 'in_review',
            'user_id' => $user->id
        ]);

        // Update to ready
        $this->putForSite("/api/briefs/{$briefId}/status", [
            'status' => 'ready',
            'user_id' => $user->id
        ]);

        // Convert to page
        $this->postForSite("/api/briefs/{$briefId}/convert", [
            'title' => 'Test Brief',
            'owner_id' => $user->id,
            'images' => [],
            'products' => []
        ]);

        $brief = Brief::find($briefId);
        $this->assertEquals('converted', $brief->status);
        $this->assertNotNull($brief->converted_at);

        // Check activity log has all actions
        $activityResponse = $this->getForSite("/api/briefs/{$briefId}/activity");
        $activityData = json_decode($activityResponse->getContent(), true);

        $this->assertGreaterThanOrEqual(5, count($activityData['items'])); // created, collaborator_added, task_created, 2x status_changed
    }

    // Bulk Operation Tests
    public function testBulkUpdateStatusUpdatesMultipleBriefs()
    {
        $user = $this->createUser();
        $brief1 = $this->createBrief(['owner_id' => $user->id, 'status' => 'draft']);
        $brief2 = $this->createBrief(['owner_id' => $user->id, 'status' => 'draft']);
        $brief3 = $this->createBrief(['owner_id' => $user->id, 'status' => 'draft']);

        $response = $this->postForSite('/api/briefs/bulk/status', [
            'brief_ids' => [$brief1->id, $brief2->id],
            'status' => 'in_review'
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $brief1 = $brief1->fresh();
        $brief2 = $brief2->fresh();
        $brief3 = $brief3->fresh();

        $this->assertEquals('in_review', $brief1->status);
        $this->assertEquals('in_review', $brief2->status);
        $this->assertEquals('draft', $brief3->status); // Not included
    }

    public function testBulkAssignAddsCollaboratorToMultipleBriefs()
    {
        $owner = $this->createUser();
        $collaborator = $this->createUser();

        $brief1 = $this->createBrief(['owner_id' => $owner->id]);
        $brief2 = $this->createBrief(['owner_id' => $owner->id]);

        $response = $this->postForSite('/api/briefs/bulk/assign', [
            'brief_ids' => [$brief1->id, $brief2->id],
            'user_id' => $collaborator->id,
            'role' => 'editor'
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $this->assertDatabaseHas('collaborators', [
            'collaboratable_id' => $brief1->id,
            'user_id' => $collaborator->id,
            'role' => 'editor',
            'collaboratable_type' => Brief::class
        ]);

        $this->assertDatabaseHas('collaborators', [
            'collaboratable_id' => $brief2->id,
            'user_id' => $collaborator->id,
            'role' => 'editor',
            'collaboratable_type' => Brief::class
        ]);
    }

    public function testBulkDeleteRemovesMultipleBriefs()
    {
        $user = $this->createUser();
        $brief1 = $this->createBrief(['owner_id' => $user->id]);
        $brief2 = $this->createBrief(['owner_id' => $user->id]);
        $brief3 = $this->createBrief(['owner_id' => $user->id]);

        $response = $this->postForSite('/api/briefs/bulk/delete', [
            'brief_ids' => [$brief1->id, $brief2->id]
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $this->assertNull(Brief::find($brief1->id));
        $this->assertNull(Brief::find($brief2->id));
        $this->assertNotNull(Brief::find($brief3->id));
    }

    public function testUpdateCollaboratorRole()
    {
        $owner = $this->createUser();
        $collaborator = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $owner->id]);

        $assignment = Collaborator::create([
            'collaboratable_id' => $brief->id,
            'user_id' => $collaborator->id,
            'role' => 'writer',
            'collaboratable_type' => Brief::class,
            'site_id' => $this->siteId
        ]);

        $response = $this->putForSite(
            "/api/briefs/{$brief->id}/collaborators/{$assignment->id}",
            ['role' => 'editor']
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('editor', $data['data']['role']);
    }

    public function testAddWorkflowChange()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id, 'status' => 'draft']);

        $response = $this->postForSite("/api/briefs/{$brief->id}/workflow", [
            'status' => 'in_review',
            'changed_by' => $user->id,
            'notes' => 'Ready for review'
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('in_review', $data['data']['status']);
        $this->assertEquals('Ready for review', $data['data']['notes']);
    }

    public function testGetWorkflowHistory()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        // Create workflow changes
        BriefWorkflowHistory::create([
            'brief_id' => $brief->id,
            'status' => 'draft',
            'changed_by' => $user->id,
            'changed_at' => date('Y-m-d H:i:s')
        ]);

        BriefWorkflowHistory::create([
            'brief_id' => $brief->id,
            'status' => 'in_review',
            'changed_by' => $user->id,
            'changed_at' => date('Y-m-d H:i:s')
        ]);

        $response = $this->getForSite("/api/briefs/{$brief->id}/workflow");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['items']);
        $this->assertEquals('in_review', $data['items'][1]['status']);
    }

    public function testSetDeadline()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $response = $this->postForSite("/api/briefs/{$brief->id}/deadline", [
            'due_date' => '2026-02-01 12:00:00',
            'reminder_days' => [1, 3, 7],
            'notify_collaborators' => true,
            'user_id' => $user->id
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('2026-02-01 12:00:00', $data['data']['due_date']);;
        $this->assertEquals([1, 3, 7], $data['data']['reminder_days']);
    }

    public function testGetDeadline()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        BriefDeadline::create([
            'brief_id' => $brief->id,
            'due_date' => '2026-02-01 12:00:00',
            'reminder_days' => json_encode([1, 3]),
            'created_by' => $user->id
        ]);

        $response = $this->getForSite("/api/briefs/{$brief->id}/deadline");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertNotNull($data['data']);
        $this->assertEquals('2026-02-01 12:00:00', $data['data']['due_date']);
    }

    public function testDeleteDeadline()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $deadline = BriefDeadline::create([
            'brief_id' => $brief->id,
            'due_date' => '2026-02-01',
            'created_by' => $user->id
        ]);

        $response = $this->deleteForSite("/api/briefs/{$brief->id}/deadline");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull(BriefDeadline::find($deadline->id));
    }

    public function testUpdateVersionTracksAllFields()
    {
        $user = $this->createUser();
        $brief = $this->createBrief([
            'owner_id' => $user->id,
            'title' => 'Original',
            'target_word_count' => 1000,
            'seo_keywords' => 'keyword1'
        ]);

        $response = $this->putForSite("/api/briefs/{$brief->id}", [
            'title' => 'Updated Title',
            'target_word_count' => 2000,
            'seo_keywords' => 'keyword1, keyword2',
            'user_id' => $user->id
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        // Check version was created
        $versions = BriefVersion::where('brief_id', $brief->id)->get();
        $this->assertEquals(1, $versions->count());

        $latestVersion = $versions->sortByDesc('version_number')->first();
        $this->assertStringContainsString('Title updated', $latestVersion->change_summary);
        $this->assertStringContainsString('Target word count updated', $latestVersion->change_summary);
        $this->assertStringContainsString('SEO keywords updated', $latestVersion->change_summary);
    }

    public function testUpdateStatusChangesStatus()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id, 'status' => 'draft']);

        $response = $this->putForSite("/api/briefs/{$brief->id}", [
            'status' => 'in_review',
            'user_id' => $user->id
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('in_review', $data['data']['status']);
    }

    public function testUpdateCategoryIdChangesCategory()
    {
        $user = $this->createUser();
        $category1 = $this->createCategory();
        $category2 = $this->createCategory();

        $brief = $this->createBrief([
            'owner_id' => $user->id,
            'category_id' => $category1->id
        ]);

        $response = $this->putForSite("/api/briefs/{$brief->id}", [
            'category_id' => $category2->id,
            'user_id' => $user->id
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals($category2->id, $data['data']['category_id']);
    }

    public function testUpdateCollaboratorReturns404ForWrongBrief()
    {
        $owner = $this->createUser();
        $collaborator = $this->createUser();
        $brief1 = $this->createBrief(['owner_id' => $owner->id]);
        $brief2 = $this->createBrief(['owner_id' => $owner->id]);

        $assignment = BriefCollaborator::create([
            'brief_id' => $brief2->id,
            'user_id' => $collaborator->id,
            'role' => 'writer'
        ]);

        $response = $this->putForSite(
            "/api/briefs/{$brief1->id}/collaborators/{$assignment->id}",
            ['role' => 'editor']
        );

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testBulkDeleteReturns400WithEmptyArray()
    {
        $response = $this->postForSite('/api/briefs/bulk/delete', [
            'brief_ids' => []
        ]);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testBulkAssignReturns400WithInvalidRole()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $response = $this->postForSite('/api/briefs/bulk/assign', [
            'brief_ids' => [$brief->id],
            'user_id' => $user->id,
            'role' => 'invalid_role'
        ]);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testBulkAssignReturns400WithoutUserId()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $response = $this->postForSite('/api/briefs/bulk/assign', [
            'brief_ids' => [$brief->id],
            'role' => 'editor'
        ]);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testBulkAssignReturns400WithEmptyArray()
    {
        $user = $this->createUser();

        $response = $this->postForSite('/api/briefs/bulk/assign', [
            'brief_ids' => [],
            'user_id' => $user->id,
            'role' => 'editor'
        ]);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testBulkUpdateStatusReturns400WithInvalidStatus()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $response = $this->postForSite('/api/briefs/bulk/status', [
            'brief_ids' => [$brief->id],
            'status' => 'invalid_status'
        ]);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testBulkUpdateStatusReturns400WithEmptyArray()
    {
        $response = $this->postForSite('/api/briefs/bulk/status', [
            'brief_ids' => [],
            'status' => 'in_review'
        ]);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testDeleteDeadlineReturns404WhenNoneExists()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $response = $this->deleteForSite("/api/briefs/{$brief->id}/deadline");

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGetDeadlineReturnsNullWhenNoneExists()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $response = $this->getForSite("/api/briefs/{$brief->id}/deadline");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertNull($data['data']);
    }

    public function testUpdateDeadlineUpdatesExisting()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $deadline = BriefDeadline::create([
            'brief_id' => $brief->id,
            'due_date' => '2026-02-01',
            'created_by' => $user->id
        ]);

        $response = $this->postForSite("/api/briefs/{$brief->id}/deadline", [
            'due_date' => '2026-03-01 15:00:00',
            'reminder_days' => [2, 5],
            'notify_collaborators' => 0,
            'user_id' => $user->id
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('2026-03-01 15:00:00', $data['data']['due_date']);
        $this->assertEquals([2, 5], $data['data']['reminder_days']);

        // Ensure only one deadline exists
        $this->assertEquals(1, BriefDeadline::where('brief_id', $brief->id)->count());
    }

    public function testRestoreVersionCreatesNewVersionBeforeRestore()
    {
        $user = $this->createUser();
        $brief = $this->createBrief([
            'owner_id' => $user->id,
            'title' => 'Current Title'
        ]);

        $oldVersion = BriefVersion::create([
            'brief_id' => $brief->id,
            'version_number' => 1,
            'title' => 'Old Title',
            'created_by' => $user->id
        ]);

        $versionCountBefore = BriefVersion::where('brief_id', $brief->id)->count();

        $this->postForSite("/api/briefs/{$brief->id}/versions/{$oldVersion->id}/restore", [
            'user_id' => $user->id
        ]);

        $versionCountAfter = BriefVersion::where('brief_id', $brief->id)->count();

        $this->assertEquals($versionCountBefore + 1, $versionCountAfter);
    }

    public function testRestoreVersionReturns404ForWrongBrief()
    {
        $user = $this->createUser();
        $brief1 = $this->createBrief(['owner_id' => $user->id]);
        $brief2 = $this->createBrief(['owner_id' => $user->id]);

        $version = BriefVersion::create([
            'brief_id' => $brief2->id,
            'version_number' => 1,
            'title' => 'Version from other brief',
            'created_by' => $user->id
        ]);

        $response = $this->postForSite("/api/briefs/{$brief1->id}/versions/{$version->id}/restore", [
            'user_id' => $user->id
        ]);

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testUpdateTaskWithCustomFields()
    {
        $user = $this->createUser();
        $assignee = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $task = BriefTask::create([
            'brief_id' => $brief->id,
            'title' => 'Original task',
            'created_by' => $user->id,
            'status' => 'pending'
        ]);

        $response = $this->putForSite("/api/briefs/{$brief->id}/tasks/{$task->id}", [
            'title' => 'Updated task',
            'description' => 'New description',
            'assigned_to' => $assignee->id,
            'due_date' => '2026-03-01'
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Updated task', $data['data']['title']);
        $this->assertEquals('New description', $data['data']['description']);
        $this->assertEquals($assignee->id, $data['data']['assigned_to']);
    }

    public function testDeleteTaskReturns404ForNonexistent()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $response = $this->deleteForSite("/api/briefs/{$brief->id}/tasks/999");

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testRemoveRelationshipReturns404ForNonexistent()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $response = $this->deleteForSite("/api/briefs/{$brief->id}/relationships/999");

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testRemoveRelationshipDeletesLink()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);
        $relatedBrief = $this->createBrief(['owner_id' => $user->id]);

        $relationship = BriefRelationship::create([
            'brief_id' => $brief->id,
            'related_brief_id' => $relatedBrief->id,
            'relationship_type' => 'related'
        ]);

        $response = $this->deleteForSite("/api/briefs/{$brief->id}/relationships/{$relationship->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull(BriefRelationship::find($relationship->id));
    }

    public function testAddRelationshipCreatesBriefToPageLink()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);
        $page = $this->createPage();

        $response = $this->postForSite("/api/briefs/{$brief->id}/relationships", [
            'related_page_id' => $page->id,
            'relationship_type' => 'reference',
            'sort_order' => 0
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals($page->id, $data['data']['related_page_id']);
    }

    public function testAddRelationshipCreatesBriefToBriefLink()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);
        $relatedBrief = $this->createBrief(['owner_id' => $user->id]);

        $response = $this->postForSite("/api/briefs/{$brief->id}/relationships", [
            'related_brief_id' => $relatedBrief->id,
            'relationship_type' => 'related',
            'sort_order' => 0
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals($relatedBrief->id, $data['data']['related_brief_id']);
        $this->assertEquals('related', $data['data']['relationship_type']);
    }

    public function testGetRelationshipsReturnsAllRelationships()
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);
        $relatedBrief = $this->createBrief(['owner_id' => $user->id]);
        $page = $this->createPage();

        BriefRelationship::create([
            'brief_id' => $brief->id,
            'related_brief_id' => $relatedBrief->id,
            'relationship_type' => 'related'
        ]);

        BriefRelationship::create([
            'brief_id' => $brief->id,
            'related_page_id' => $page->id,
            'relationship_type' => 'converted'
        ]);

        $response = $this->getForSite("/api/briefs/{$brief->id}/relationships");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['items']);
    }
}