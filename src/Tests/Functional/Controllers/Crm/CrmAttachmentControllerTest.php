<?php

namespace App\Tests\Functional\Controllers\Crm;

use App\Enums\AttachmentableType;
use App\Models\Attachment;
use App\Models\Member;
use App\Models\Model;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

/**
 * Functional tests for CrmAttachmentController.
 *
 * Routes under test:
 *   GET    /api/crm/members/{memberId}/attachments              index
 *   GET    /api/crm/members/{memberId}/attachments?entity_type=X&entity_id=Y  index (filtered)
 *   POST   /api/crm/members/{memberId}/attachments              store   (multipart)
 *   DELETE /api/crm/members/{memberId}/attachments/{id}         destroy
 *
 * Response shapes:
 *   index   → { attachments: [...] }          200
 *   store   → { attachment: {...} }           201
 *   destroy → { success: true }               200
 *   errors  → { error: '...', success: false } 4xx / 5xx
 */
class CrmAttachmentControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;

    // ── index (all attachments for member) ────────────────────────────────────

    public function test_index_returns_200_with_attachments_for_member(): void
    {
        $this->createAttachment(['member_id' => $this->member->id]);
        $this->createAttachment(['member_id' => $this->member->id]);

        $response = $this->getForSite('/api/crm/members/' . $this->member->id . '/attachments');

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('attachments', $data);
        $this->assertCount(2, $data['attachments']);
    }

    public function test_index_returns_empty_list_when_member_has_no_attachments(): void
    {
        $response = $this->getForSite('/api/crm/members/' . $this->member->id . '/attachments');

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertCount(0, $data['attachments']);
    }

    public function test_index_returns_401_for_unauthenticated_agent(): void
    {
        $this->unauthenticate();

        $response = $this->getForSite('/api/crm/members/' . $this->member->id . '/attachments');

        $this->assertResponseStatus(401, $response);
    }

    public function test_index_only_returns_attachments_for_requested_member(): void
    {
        $otherMember = $this->createMember();
        $this->createAttachment(['member_id' => $this->member->id, 'original_filename' => 'mine.pdf']);
        $this->createAttachment(['member_id' => $otherMember->id, 'original_filename' => 'theirs.pdf']);

        $response = $this->getForSite('/api/crm/members/' . $this->member->id . '/attachments');

        $data      = json_decode($response->getContent(), true);
        $filenames = array_column($data['attachments'], 'original_filename');

        $this->assertContains('mine.pdf', $filenames);
        $this->assertNotContains('theirs.pdf', $filenames);
    }

    // ── index (filtered by entity) ────────────────────────────────────────────

    public function test_index_filtered_by_entity_type_and_id_returns_matching_attachments(): void
    {
        $entityType = AttachmentableType::MANUAL_PAYMENT->value;
        $entityId   = 55;

        $this->createAttachment([
            'member_id'           => $this->member->id,
            'attachmentable_type' => $entityType,
            'attachmentable_id'   => $entityId,
            'original_filename'   => 'receipt.pdf',
        ]);
        $this->createAttachment([
            'member_id'           => $this->member->id,
            'attachmentable_type' => $entityType,
            'attachmentable_id'   => 999, // different entity
            'original_filename'   => 'other.pdf',
        ]);

        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/attachments'
            . "?entity_type={$entityType}&entity_id={$entityId}"
        );

        $this->assertResponseStatus(200, $response);

        $data      = json_decode($response->getContent(), true);
        $filenames = array_column($data['attachments'], 'original_filename');

        $this->assertContains('receipt.pdf', $filenames);
        $this->assertNotContains('other.pdf', $filenames);
    }

    // ── store ─────────────────────────────────────────────────────────────────

    /**
     * File upload requires the test framework to support multipart requests.
     * The APP_ENV=testing guard in AttachmentService bypasses moveTo(), so the
     * record will be created even without a real file on disk.
     */
    public function test_store_creates_attachment_record_and_returns_201(): void
    {
        $payload = $this->validUploadPayload();

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/attachments',
            $payload,
            $this->makeRawFile()
        );

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('attachment', $data);
        $this->assertNotEmpty($data['attachment']['id']);

        $this->assertDatabaseHas('attachments', [
            'member_id'           => $this->member->id,
            'attachmentable_type' => AttachmentableType::MANUAL_PAYMENT->value,
        ]);
    }

    public function test_store_returns_422_when_no_file_provided(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/attachments',
            ['entity_type' => AttachmentableType::MANUAL_PAYMENT->value, 'entity_id' => 1],
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_store_returns_422_when_entity_type_missing(): void
    {
        $payload = $this->validUploadPayload();
        unset($payload['entity_type']);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/attachments',
            $payload,
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_store_returns_422_when_entity_id_missing(): void
    {
        $payload = $this->validUploadPayload();
        unset($payload['entity_id']);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/attachments',
            $payload,
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_store_returns_422_for_invalid_entity_type_value(): void
    {
        $payload                = $this->validUploadPayload();
        $payload['entity_type'] = 'not_a_valid_type';

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/attachments',
            $payload,
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_store_returns_401_for_unauthenticated_agent(): void
    {
        $this->unauthenticate();

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/attachments',
            $this->validUploadPayload(),
        );

        $this->assertResponseStatus(401, $response);
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_deletes_attachment_and_returns_success(): void
    {
        $attachment = $this->createAttachment(['member_id' => $this->member->id]);

        $response = $this->deleteForSite(
            '/api/crm/members/' . $this->member->id . '/attachments/' . $attachment->id
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    }

    public function test_destroy_returns_422_when_attachment_belongs_to_different_member(): void
    {
        $otherMember = $this->createMember();
        $attachment  = $this->createAttachment(['member_id' => $otherMember->id]);

        $response = $this->deleteForSite(
            '/api/crm/members/' . $this->member->id . '/attachments/' . $attachment->id
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_destroy_returns_422_when_attachment_not_found(): void
    {
        $response = $this->deleteForSite(
            '/api/crm/members/' . $this->member->id . '/attachments/999999'
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_destroy_returns_401_for_unauthenticated_agent(): void
    {
        $this->unauthenticate();
        $attachment = $this->createAttachment(['member_id' => $this->member->id]);

        $response = $this->deleteForSite(
            '/api/crm/members/' . $this->member->id . '/attachments/' . $attachment->id
        );

        $this->assertResponseStatus(401, $response);
    }

    // ── setup / helpers ───────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = $this->createMember([
            'first_name' => 'Test',
            'last_name'  => 'User',
            'email'      => 'attach.test.' . uniqid() . '@example.com',
            'is_active'  => true,
            'anonymous'  => false,
        ]);
    }

    private function createAttachment(array $overrides = []): Model
    {
        return Attachment::create(array_merge([
            'member_id'           => $this->member->id,
            'site_id'             => $this->siteId,
            'attachmentable_type' => AttachmentableType::MANUAL_PAYMENT->value,
            'attachmentable_id'   => 1,
            'original_filename'   => 'document.pdf',
            'stored_path'         => 'crm/attachments/' . $this->member->id . '/document.pdf',
            'mime_type'           => 'application/pdf',
            'file_size'           => 1024,
            'uploaded_by'         => 1,
        ], $overrides));
    }

    private function validUploadPayload(array $overrides = []): array
    {
        return array_merge([
            'entity_type' => AttachmentableType::MANUAL_PAYMENT->value,
            'entity_id'   => 1,
            // 'file' would be a real UploadedFile in a multipart request.
            // How this is simulated depends on the test framework's postFile() helper.
            'file'        => $this->makeUploadedFile(),
        ], $overrides);
    }

    /**
     * Creates a minimal in-memory uploaded file for tests.
     * Replace with your framework's equivalent (e.g. UploadedFile::fake()) if available.
     */
    private function makeUploadedFile(): mixed
    {
        // Adjust to match the test framework's file-upload abstraction.
        return new \App\Framework\Http\UploadedFile(
            ['tmp_name' => tempnam(sys_get_temp_dir(), 'test_'),
            'name' => 'test.pdf',
            'type' => 'application/pdf']
        );
    }

    private function makeRawFile(): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test_');

        file_put_contents($tmp, 'fake pdf');

        return ['file' => [
            'tmp_name' => $tmp,
            'name'     => 'test.pdf',
            'type'     => 'application/pdf',
            'size'     => filesize($tmp),
            'error'    => UPLOAD_ERR_OK,
        ]];
    }
}