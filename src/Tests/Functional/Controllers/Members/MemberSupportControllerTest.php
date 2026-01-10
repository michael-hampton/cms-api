<?php

namespace App\Tests\Functional\Controllers\Members;

use App\Models\Member;
use App\Models\Subscription;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberSupportControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;

    public function test_support_page_requires_authentication()
    {
        $response = $this->getForSiteUnauthenticated('/member/support');
        $this->assertResponseStatus(302, $response);
    }

    public function test_authenticated_member_can_view_support_page()
    {
        $this->actingAsMember($this->member);

        $response = $this->getForSiteUnauthenticated('/member/support');

        $this->assertResponseOk($response);
        $this->assertStringContainsString('reason', $response->getContent());
    }

    public function test_can_submit_support_ticket_with_required_fields()
    {
        $this->actingAsMember($this->member);

        $data = [
            'reason' => 'billing_question',
            'message' => 'I have a question about my recent charge.',
            'contact_name' => 'John Doe',
            'contact_email' => 'john@example.com'
        ];

        $response = $this->postForSiteUnauthenticated('/member/support/submit', $data);

        $this->assertJson($response->getContent());
        $result = json_decode($response->getContent(), true);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('ticket_id', $result);

        // Verify ticket was created in database
        $this->assertDatabaseHas('support_tickets', [
            'member_id' => $this->member->id,
            'reason' => 'billing_question',
            'status' => 'open'
        ]);
    }

    public function test_cannot_submit_support_ticket_without_required_fields()
    {
        $this->actingAsMember($this->member);

        $data = [
            'reason' => '',
            'message' => ''
        ];

        $response = $this->postForSiteUnauthenticated('/member/support/submit', $data);

        $this->assertResponseStatus(400, $response);
        $result = json_decode($response->getContent(), true);
        $this->assertFalse($result['success']);
    }

    public function test_can_submit_ticket_with_subscription_reference()
    {
        $this->actingAsMember($this->member);

        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'status' => 'active',
            'plan_name' => 'Test plan',
            'start_date' => now(),
        ]);

        $data = [
            'reason' => 'delivery_issue',
            'subscription_id' => $subscription->id,
            'brand' => 'kiplinger',
            'message' => 'My delivery has not arrived.',
            'contact_name' => 'John Doe',
            'contact_email' => 'john@example.com'
        ];

        $response = $this->postForSiteUnauthenticated('/member/support/submit', $data);

        $result = json_decode($response->getContent(), true);

        $this->assertTrue($result['success']);

        $this->assertDatabaseHas('support_tickets', [
            'member_id' => $this->member->id,
            'subscription_id' => $subscription->id,
            'brand' => 'kiplinger'
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = $this->createMember();
    }
}