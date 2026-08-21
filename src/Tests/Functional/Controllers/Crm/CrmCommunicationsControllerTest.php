<?php

namespace App\Tests\Functional\Controllers\Crm;

use App\Framework\Database\Database;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

/**
 * Functional tests for CrmCommunicationsController.
 *
 * Route under test:
 *   GET /api/crm/members/{memberId}/communications
 */
class CrmCommunicationsControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private function createCommunicationLog(int $memberId, array $overrides = []): int
    {
        return Database::table('communication_logs')->insert(array_merge([
            'member_id' => $memberId,
            'type' => 'transactional',
            'channel' => 'email',
            'subject' => 'Test subject ' . uniqid(),
            'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    public function test_index_returns_200_with_communications_and_pagination(): void
    {
        $member = $this->createMember();
        $this->createCommunicationLog($member->id);
        $this->createCommunicationLog($member->id);

        $response = $this->getForSite('/api/crm/members/' . $member->id . '/communications');

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('communications', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertCount(2, $data['communications']);
    }

    public function test_index_filters_by_created_at_range(): void
    {
        $member = $this->createMember();
        $inRangeId = $this->createCommunicationLog($member->id, ['subject' => 'In Range Subject']);
        $outOfRangeId = $this->createCommunicationLog($member->id, ['subject' => 'Out Of Range Subject']);

        Database::table('communication_logs')->where('id', $inRangeId)->update(['created_at' => '2026-03-15 10:00:00']);
        Database::table('communication_logs')->where('id', $outOfRangeId)->update(['created_at' => '2026-01-01 10:00:00']);

        $response = $this->getForSite(
            '/api/crm/members/' . $member->id . '/communications?date_from=2026-03-01&date_to=2026-03-31'
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $subjects = array_column($data['communications'], 'subject');
        $this->assertContains('In Range Subject', $subjects);
        $this->assertNotContains('Out Of Range Subject', $subjects);
    }

    public function test_index_filters_by_updated_at_range(): void
    {
        $member = $this->createMember();
        $inRangeId = $this->createCommunicationLog($member->id, ['subject' => 'Recently Updated Subject']);
        $outOfRangeId = $this->createCommunicationLog($member->id, ['subject' => 'Stale Subject']);

        Database::table('communication_logs')->where('id', $inRangeId)->update(['updated_at' => '2026-03-15 10:00:00']);
        Database::table('communication_logs')->where('id', $outOfRangeId)->update(['updated_at' => '2026-01-01 10:00:00']);

        $response = $this->getForSite(
            '/api/crm/members/' . $member->id . '/communications?updated_from=2026-03-01&updated_to=2026-03-31'
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $subjects = array_column($data['communications'], 'subject');
        $this->assertContains('Recently Updated Subject', $subjects);
        $this->assertNotContains('Stale Subject', $subjects);
    }
}
