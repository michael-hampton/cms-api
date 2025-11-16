<?php

namespace App\Tests\Unit\Mail;

use App\Mail\DealAlert;
use App\Models\Member;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class DealAlertTest extends FunctionalTestCase
{
    public function testBuildsSetsCorrectSubject(): void
    {
        $member = $this->createMockMember();
        $deals = $this->createMockDeals();

        $mailable = new DealAlert($member, $deals);
        $mailable->build();

        $this->assertStringContainsString('Hot Deals Alert', $mailable->subject);
    }

    private function createMockMember(): Member
    {
        $member = new Member();
        $member->id = 1;
        $member->email = 'test@example.com';
        $member->first_name = 'John';
        return $member;
    }

    private function createMockDeals(int $count = 2): array
    {
        $deals = [];
        for ($i = 1; $i <= $count; $i++) {
            $deals[] = [
                'id' => $i,
                'product_name' => "Deal Product {$i}",
                'description' => "Great deal on product {$i}",
                'original_price' => 100.00,
                'deal_price' => 70.00,
                'discount_percentage' => 30,
                'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days'))
            ];
        }
        return $deals;
    }

    public function testIncludesDealCount(): void
    {
        $member = $this->createMockMember();
        $deals = $this->createMockDeals(3);

        $mailable = new DealAlert($member, $deals);
        $mailable->build();

        $this->assertEquals(3, $mailable->viewData['dealCount']);
    }

    public function testRendersAllDeals(): void
    {
        $member = $this->createMockMember();
        $deals = $this->createMockDeals(2);

        $mailable = new DealAlert($member, $deals);
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString('Deal Product 1', $html);
        $this->assertStringContainsString('Deal Product 2', $html);
    }
}