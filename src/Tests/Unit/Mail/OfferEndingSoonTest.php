<?php

namespace App\Tests\Unit\Mail;

use App\Mail\Offers\OfferEndingSoon;
use App\Models\Member;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class OfferEndingSoonTest extends FunctionalTestCase
{
    public function testBuildsSetsCorrectSubject(): void
    {
        $member = $this->createMockMember();
        $offer = $this->createMockOffer();
        $hoursRemaining = 24;

        $mailable = new OfferEndingSoon($member, $offer, $hoursRemaining);
        $mailable->build();

        $this->assertStringContainsString('Last Chance', $mailable->subject);
        $this->assertStringContainsString('24 Hours', $mailable->subject);
    }

    private function createMockMember(): Member
    {
        $member = new Member();
        $member->id = 1;
        $member->email = 'shopper@example.com';
        $member->first_name = 'Sam';
        $member->last_name = 'Wilson';
        return $member;
    }

    private function createMockOffer(): ProductOffer
    {
        $product = new Product();
        $product->id = 1;
        $product->name = 'Smart Watch';
        $product->slug = 'smart-watch';

        $offer = new ProductOffer();
        $offer->id = 1;
        $offer->product_id = 1;
        $offer->title = '40% Off Smart Watch';
        $offer->description = 'Final hours to save big!';
        $offer->discount_type = 'percentage';
        $offer->discount_value = 40;
        $offer->start_date = date('Y-m-d H:i:s', strtotime('-6 days'));
        $offer->end_date = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $offer->status = 'published';
        $offer->product = $product;

        return $offer;
    }

    public function testIncludesHoursRemaining(): void
    {
        $member = $this->createMockMember();
        $offer = $this->createMockOffer();
        $hoursRemaining = 12;

        $mailable = new OfferEndingSoon($member, $offer, $hoursRemaining);
        $mailable->build();

        $this->assertEquals($hoursRemaining, $mailable->viewData['hoursRemaining']);
    }

    public function testRendersWithUrgency(): void
    {
        $member = $this->createMockMember();
        $offer = $this->createMockOffer();

        $mailable = new OfferEndingSoon($member, $offer, 6);
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString('Smart Watch', $html);
        $this->assertStringContainsString($member->first_name, $html);
    }

    public function testIncludesEndDate(): void
    {
        $member = $this->createMockMember();
        $offer = $this->createMockOffer();

        $mailable = new OfferEndingSoon($member, $offer, 3);
        $mailable->build();

        $this->assertEquals($offer->end_date, $mailable->viewData['endDate']);
    }

    public function testUsesMarkdownTemplate(): void
    {
        $member = $this->createMockMember();
        $offer = $this->createMockOffer();

        $mailable = new OfferEndingSoon($member, $offer, 24);
        $mailable->build();

        $this->assertEquals('emails.offers.ending-soon', $mailable->markdown);
    }
}