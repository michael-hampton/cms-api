<?php

namespace App\Tests\Unit\Repositories\Offers;

use App\Models\DealClick;
use App\Repositories\Offers\DealClickRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class DealClickRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private DealClickRepository $repository;

    public function testTrackClickCreatesRecord(): void
    {
        $product = $this->createProduct();
        $member = $this->createMember();

        $click = $this->repository->trackClick(
            $product->id,
            $member->id,
            $this->siteId,
            'render',
            '127.0.0.1',
            'Mozilla/5.0',
            [
                'channel' => 'newsletter',
                'surface_type' => 'newsletter_issue',
                'surface_id' => 123,
            ]
        );

        $this->assertInstanceOf(DealClick::class, $click);
        $this->assertEquals($product->id, $click->product_id);
        $this->assertEquals($member->id, $click->member_id);
        $this->assertEquals($this->siteId, $click->site_id);
        $this->assertEquals('render', $click->action);
        $this->assertEquals('newsletter', $click->channel);
        $this->assertEquals('newsletter_issue', $click->surface_type);
        $this->assertEquals(123, $click->surface_id);
    }

    public function testTrackClickWithoutMember(): void
    {
        $product = $this->createProduct();

        $click = $this->repository->trackClick(
            $product->id,
            null,
            $this->siteId,
            'click',
            '127.0.0.1',
            'Mozilla/5.0'
        );

        $this->assertNull($click->member_id);
        $this->assertEquals('click', $click->action);
    }

    public function testGetClicks(): void
    {
        $product = $this->createProduct();

        $this->repository->trackClick($product->id, null, $this->siteId, 'render');
        $this->repository->trackClick($product->id, null, $this->siteId, 'click');
        $this->repository->trackClick($product->id, null, $this->siteId, 'render');

        $allClicks = $this->repository->getClicks($product->id);
        $this->assertCount(3, $allClicks);

        $renderClicks = $this->repository->getClicks($product->id, 'render');
        $this->assertCount(2, $renderClicks);
    }

    public function testGetClickCount(): void
    {
        $product = $this->createProduct();

        $this->repository->trackClick($product->id, null, $this->siteId, 'render');
        $this->repository->trackClick($product->id, null, $this->siteId, 'render');
        $this->repository->trackClick($product->id, null, $this->siteId, 'click');

        $renderCount = $this->repository->getClickCount($product->id, 'render');
        $this->assertEquals(2, $renderCount);

        $clickCount = $this->repository->getClickCount($product->id, 'click');
        $this->assertEquals(1, $clickCount);
    }

    public function testGetClicksByMember(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();
        $member = $this->createMember();

        $this->repository->trackClick($product1->id, $member->id, $this->siteId, 'render');
        $this->repository->trackClick($product2->id, $member->id, $this->siteId, 'click');
        $this->repository->trackClick($product1->id, $member->id, $this->siteId, 'render');

        $allClicks = $this->repository->getClicksByMember($member->id);
        $this->assertCount(3, $allClicks);

        $renderClicks = $this->repository->getClicksByMember($member->id, 'render');
        $this->assertCount(2, $renderClicks);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new DealClickRepository();
    }
}