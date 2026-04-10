<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Enums\OpenCollab\PaymentStatus;
use App\Models\ArticlePayment;
use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ContributorDashboardControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private User $contributor;

    public function test_dashboard_returns_zero_earnings_when_no_payments_exist(): void
    {
        $this->actingAs($this->contributor);

        $response = $this->getForSite('/api/open-collab/dashboard');

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(0, $data['data']['total_pence']);
        $this->assertEquals(0, $data['data']['total_pounds']);
        $this->assertEmpty($data['data']['breakdown']);
    }

    public function test_dashboard_returns_correct_total_from_succeeded_payments(): void
    {
        $this->actingAs($this->contributor);

        $pageA = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'is_paid' => true,
            'price' => 500,
            'status' => 'published',
        ]);

        $pageB = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'is_paid' => true,
            'price' => 1000,
            'status' => 'published',
        ]);

        // Two succeeded payments
        ArticlePayment::create([
            'site_id' => $this->siteId,
            'page_id' => $pageA->id,
            'user_id' => null,
            'email' => 'a@example.com',
            'stripe_payment_intent_id' => 'pi_a1',
            'status' => PaymentStatus::Succeeded->value,
            'amount' => 500,
            'currency' => 'gbp',
        ]);

        ArticlePayment::create([
            'site_id' => $this->siteId,
            'page_id' => $pageB->id,
            'user_id' => null,
            'email' => 'b@example.com',
            'stripe_payment_intent_id' => 'pi_b1',
            'status' => PaymentStatus::Succeeded->value,
            'amount' => 1000,
            'currency' => 'gbp',
        ]);

        // Pending payment must NOT count
        ArticlePayment::create([
            'site_id' => $this->siteId,
            'page_id' => $pageA->id,
            'user_id' => null,
            'email' => 'c@example.com',
            'stripe_payment_intent_id' => 'pi_c1',
            'status' => PaymentStatus::Pending->value,
            'amount' => 500,
            'currency' => 'gbp',
        ]);

        $response = $this->getForSite('/api/open-collab/dashboard');

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(1500, $data['data']['total_pence']);
        $this->assertEquals('15.00', $data['data']['total_pounds']);
        $this->assertCount(2, $data['data']['breakdown']);
    }

    public function test_dashboard_does_not_include_other_contributors_earnings(): void
    {
        $otherContributor = $this->createUser([
            'email' => 'other@example.com',
            'role' => 'contributor',
            'is_contributor' => true,
        ]);

        $this->actingAs($this->contributor);

        $otherPage = $this->createPage([
            'contributor_id' => $otherContributor->id,
            'is_paid' => true,
            'price' => 5000,
            'status' => 'published',
        ]);

        ArticlePayment::create([
            'site_id' => $this->siteId,
            'page_id' => $otherPage->id,
            'user_id' => null,
            'email' => 'buyer@example.com',
            'stripe_payment_intent_id' => 'pi_other',
            'status' => PaymentStatus::Succeeded->value,
            'amount' => 5000,
            'currency' => 'gbp',
        ]);

        $response = $this->getForSite('/api/open-collab/dashboard');

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(0, $data['data']['total_pence']);
    }

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $response = $this->getForSiteUnauthenticated('/api/open-collab/dashboard');

        $this->assertEquals(401, $response->getStatusCode());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->contributor = $this->createUser([
            'role' => 'contributor',
            'is_contributor' => true,
        ]);
    }
}