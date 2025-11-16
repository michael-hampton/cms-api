<?php

namespace App\Tests\Unit\Mail;

use App\Mail\PriceAlert;
use App\Models\Member;
use App\Models\Product;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class PriceAlertTest extends FunctionalTestCase
{
    public function testBuildsSetsCorrectSubject(): void
    {
        $product = $this->createMockProduct();
        $member = $this->createMockMember();

        $mailable = new PriceAlert($product, $member, 99.99, 79.99, 80.00);
        $mailable->build();

        $this->assertStringContainsString('Price Drop Alert', $mailable->subject);
        $this->assertStringContainsString($product->name, $mailable->subject);
    }

    private function createMockProduct(): Product
    {
        $product = new Product();
        $product->id = 1;
        $product->name = 'Test Product';
        $product->slug = 'test-product';
        $product->description = 'A great test product';
        $product->stock = 10;
        return $product;
    }

    private function createMockMember(): Member
    {
        $member = new Member();
        $member->id = 1;
        $member->email = 'test@example.com';
        $member->first_name = 'John';
        $member->last_name = 'Doe';
        return $member;
    }

    public function testCalculatesPercentageOff(): void
    {
        $product = $this->createMockProduct();
        $member = $this->createMockMember();

        $mailable = new PriceAlert($product, $member, 100.00, 80.00, 80.00);
        $mailable->build();

        $this->assertEquals(20, $mailable->viewData['percentageOff']);
    }

    public function testCalculatesSavings(): void
    {
        $product = $this->createMockProduct();
        $member = $this->createMockMember();

        $mailable = new PriceAlert($product, $member, 100.00, 75.00, 80.00);
        $mailable->build();

        $this->assertEquals(25.00, $mailable->viewData['savings']);
    }

    public function testRendersWithAllData(): void
    {
        $product = $this->createMockProduct();
        $member = $this->createMockMember();

        $mailable = new PriceAlert($product, $member, 99.99, 79.99, 80.00);
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString($product->name, $html);
        $this->assertStringContainsString($member->first_name, $html);
        $this->assertStringContainsString('79.99', $html);
    }
}