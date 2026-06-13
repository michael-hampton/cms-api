<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Enums\Pages\PageStatus;
use App\Enums\OpenCollab\OnboardingStepStatus;
use App\Models\ContributorOnboardingStep;
use App\Models\ContributorProfile;
use App\Models\Site;
use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ArticleApprovalControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private User $contributor;
    private User $otherContributor;

    public function test_pending_returns_waiting_approval_articles_for_site(): void
    {
        $pending = $this->createPage([
            'title' => 'Pending Review',
            'contributor_id' => $this->contributor->id,
            'status' => PageStatus::WAITING_APPROVAL->value,
            'submitted_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);

        $this->createPage([
            'title' => 'Published',
            'contributor_id' => $this->contributor->id,
            'status' => PageStatus::PUBLISHED->value,
        ]);

        $response = $this->getForSite('/api/open-collab/admin/articles/pending');

        $data = json_decode($response->getContent(), true);
        $items = array_values(array_filter($data, static fn($key) => is_int($key), ARRAY_FILTER_USE_KEY));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $items);
        $this->assertEquals($pending->id, $items[0]['id']);
    }

    public function test_admin_can_approve_waiting_article(): void
    {
        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'status' => PageStatus::WAITING_APPROVAL->value,
        ]);

        $response = $this->postForSite("/api/open-collab/admin/articles/{$page->id}/approve");

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(PageStatus::PUBLISHED->value, $data['data']['page']['status']);
        $this->assertDatabaseHas('pages', ['id' => $page->id, 'status' => PageStatus::PUBLISHED->value]);
    }

    public function test_admin_can_reject_waiting_article(): void
    {
        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'status' => PageStatus::WAITING_APPROVAL->value,
        ]);

        $response = $this->postForSite("/api/open-collab/admin/articles/{$page->id}/reject", [
            'reason' => 'quality',
            'notes' => 'Needs stronger sourcing.',
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('pages', [
            'id' => $page->id,
            'status' => PageStatus::REJECTED->value,
            'rejection_reason' => 'quality',
        ]);
    }

    public function test_reject_returns_validation_errors_for_invalid_request(): void
    {
        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'status' => PageStatus::WAITING_APPROVAL->value,
        ]);

        $response = $this->postForSite("/api/open-collab/admin/articles/{$page->id}/reject", [
            'reason' => 'not-a-real-reason',
        ]);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('Validation failed', $data['error']);
        $this->assertArrayHasKey('reason', $data['errors']);
    }

    public function test_contributor_can_submit_their_own_article_for_review(): void
    {
        $this->actingAs($this->contributor);

        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'status' => PageStatus::DRAFT->value,
        ]);

        $response = $this->postForSite("/api/open-collab/pages/{$page->id}/submit");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('pages', ['id' => $page->id, 'status' => PageStatus::WAITING_APPROVAL->value]);
    }

    public function test_contributor_cannot_submit_their_own_article_for_review_when_onboarding_incomplete(): void
    {
        $this->actingAs($this->contributor);

        $this->setupSiteOnboarding(true);

        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'status' => PageStatus::DRAFT->value,
        ]);

        $response = $this->postForSite("/api/open-collab/pages/{$page->id}/submit");

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_submit_returns_403_for_page_owned_by_another_contributor(): void
    {
        $this->actingAs($this->contributor);

        $page = $this->createPage([
            'contributor_id' => $this->otherContributor->id,
            'status' => PageStatus::DRAFT->value,
        ]);

        $response = $this->postForSite("/api/open-collab/pages/{$page->id}/submit");

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_contributor_can_resubmit_on_hold_article(): void
    {
        $this->actingAs($this->contributor);

        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'status' => PageStatus::REJECTED->value,
            'resubmission_count' => 1,
        ]);

        $response = $this->postForSite("/api/open-collab/pages/{$page->id}/resubmit");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('pages', [
            'id' => $page->id,
            'status' => PageStatus::WAITING_APPROVAL->value,
            'resubmission_count' => 2,
        ]);
    }

    private function setupSiteOnboarding(bool $requiresSiteOnboarding = false): void
    {
        $this->ensureSiteExists();
        $site = Site::find($this->siteId);

        $site->update([
            'require_payment_setup' => $requiresSiteOnboarding,
            'require_contracts' => $requiresSiteOnboarding,
            'require_guidelines_ack' => $requiresSiteOnboarding,
            'require_age_verification' => $requiresSiteOnboarding,
        ]);
    }

    private function completeProfileOnboarding(User $contributor): void
    {
        ContributorOnboardingStep::updateOrCreate(
            [
                'user_id' => $contributor->id,
                'site_id' => $this->siteId,
                'step' => 'profile',
            ],
            [
                'status' => OnboardingStepStatus::Completed->value,
                'completed_at' => date('Y-m-d H:i:s'),
            ],
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->contributor = $this->createUser([
            'email' => 'article-owner@example.com',
            'role' => 'contributor',
            'is_contributor' => true,
        ]);

        ContributorProfile::firstOrCreate(
            ['user_id' => $this->contributor->id],
            ['bio' => 'test bio', 'date_of_birth' => '1990-01-01'],
        );

        $this->setupSiteOnboarding();
        $this->completeProfileOnboarding($this->contributor);

        $this->otherContributor = $this->createUser([
            'email' => 'other-article-owner@example.com',
            'role' => 'contributor',
            'is_contributor' => true,
        ]);

        ContributorProfile::firstOrCreate(
            ['user_id' => $this->otherContributor->id],
            ['bio' => 'test bio', 'date_of_birth' => '1990-01-01'],
        );
        $this->completeProfileOnboarding($this->otherContributor);
    }
}
