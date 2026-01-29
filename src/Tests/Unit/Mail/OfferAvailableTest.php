<?php

namespace App\Tests\Unit\Mail;

use App\Mail\OfferAvailable;
use App\Models\Member;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class OfferAvailableTest extends FunctionalTestCase
{
    public function testBuildsSetsCorrectSubject(): void
    {
        $member = $this->createMockMember();
        $offer = $this->createMockOffer();

        $mailable = new OfferAvailable($member, $offer);
        $mailable->build();

        $this->assertStringContainsString('Special Offer', $mailable->subject);
    }

    private function createMockMember(): Member
    {
        $member = new Member();
        $member->id = 1;
        $member->email = 'customer@example.com';
        $member->first_name = 'Alex';
        $member->last_name = 'Brown';
        return $member;
    }

    private function createMockOffer(): ProductOffer
    {
        $product = new Product();
        $product->id = 1;
        $product->name = 'Premium Headphones';
        $product->slug = 'premium-headphones';
        $product->description = 'High-quality wireless headphones';

        $offer = new ProductOffer();
        $offer->id = 1;
        $offer->product_id = 1;
        $offer->title = '30% Off Premium Headphones';
        $offer->description = 'Limited time offer on our best-selling headphones';
        $offer->discount_type = 'percentage';
        $offer->discount_value = 30;
        $offer->start_date = date('Y-m-d H:i:s');
        $offer->end_date = date('Y-m-d H:i:s', strtotime('+7 days'));
        $offer->status = 'published';
        $offer->product = $product;

        return $offer;
    }

    public function testIncludesOfferDetails(): void
    {
        $member = $this->createMockMember();
        $offer = $this->createMockOffer();

        $mailable = new OfferAvailable($member, $offer);
        $mailable->build();

        $this->assertEquals($offer, $mailable->viewData['offer']);
        $this->assertEquals($offer->product, $mailable->viewData['product']);
    }

    public function testRendersWithProductInfo(): void
    {
        $member = $this->createMockMember();
        $offer = $this->createMockOffer();

        $mailable = new OfferAvailable($member, $offer);
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString('Premium Headphones', $html);
        $this->assertStringContainsString($member->first_name, $html);
    }

    public function testIncludesOfferDates(): void
    {
        $member = $this->createMockMember();
        $offer = $this->createMockOffer();

        $mailable = new OfferAvailable($member, $offer);
        $mailable->build();

        $this->assertEquals($offer->start_date, $mailable->viewData['startDate']);
        $this->assertEquals($offer->end_date, $mailable->viewData['endDate']);
    }

    public function testUsesMarkdownTemplate(): void
    {
        $member = $this->createMockMember();
        $offer = $this->createMockOffer();

        $mailable = new OfferAvailable($member, $offer);
        $mailable->build();

        $this->assertEquals('emails.offers.available', $mailable->markdown);
    }
}