<?php

namespace App\Tests\Unit\Services\Comments;

use App\DTO\Comments\CreateCommentDTO;
use App\Services\Members\Comments\SimpleSpamDetector;
use PHPUnit\Framework\TestCase;

class SimpleSpamDetectorTest extends TestCase
{
    private SimpleSpamDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new SimpleSpamDetector();
    }

    public function testDetectsSpamKeywords()
    {
        $dto = new CreateCommentDTO(
            content: 'Buy cheap viagra now!',
            pageId: 1,
            memberId: null,
            name: 'Spammer',
            email: 'spam@test.com',
            parentId: null,
            siteId: 1
        );

        $result = $this->detector->isSpam($dto);

        $this->assertTrue($result);
    }

    public function testDetectsExcessiveLinks()
    {
        $dto = new CreateCommentDTO(
            content: 'Check out http://spam1.com http://spam2.com http://spam3.com http://spam4.com',
            pageId: 1,
            memberId: null,
            name: 'Spammer',
            email: 'spam@test.com',
            parentId: null,
            siteId: 1
        );

        $result = $this->detector->isSpam($dto);

        $this->assertTrue($result);
    }

    public function testAllowsLegitimateContent()
    {
        $dto = new CreateCommentDTO(
            content: 'Great article! Very informative.',
            pageId: 1,
            memberId: null,
            name: 'Legitimate User',
            email: 'user@test.com',
            parentId: null,
            siteId: 1
        );

        $result = $this->detector->isSpam($dto);

        $this->assertFalse($result);
    }

    public function testAllowsContentWithFewLinks()
    {
        $dto = new CreateCommentDTO(
            content: 'Check out http://example.com and http://example2.com',
            pageId: 1,
            memberId: null,
            name: 'User',
            email: 'user@test.com',
            parentId: null,
            siteId: 1
        );

        $result = $this->detector->isSpam($dto);

        $this->assertFalse($result);
    }

    public function testDetectsCasinoKeyword()
    {
        $dto = new CreateCommentDTO(
            content: 'Win big at our casino!',
            pageId: 1,
            memberId: null,
            name: 'Spammer',
            email: 'spam@test.com',
            parentId: null,
            siteId: 1
        );

        $result = $this->detector->isSpam($dto);

        $this->assertTrue($result);
    }

    public function testDetectsLotteryKeyword()
    {
        $dto = new CreateCommentDTO(
            content: 'You won the lottery!',
            pageId: 1,
            memberId: null,
            name: 'Spammer',
            email: 'spam@test.com',
            parentId: null,
            siteId: 1
        );

        $result = $this->detector->isSpam($dto);

        $this->assertTrue($result);
    }

    public function testCaseInsensitiveKeywordDetection()
    {
        $dto = new CreateCommentDTO(
            content: 'Buy cheap VIAGRA now!',
            pageId: 1,
            memberId: null,
            name: 'Spammer',
            email: 'spam@test.com',
            parentId: null,
            siteId: 1
        );

        $result = $this->detector->isSpam($dto);

        $this->assertTrue($result);
    }

    public function testCountsBothHttpAndHttpsLinks()
    {
        $dto = new CreateCommentDTO(
            content: 'http://1.com http://2.com https://3.com https://4.com',
            pageId: 1,
            memberId: null,
            name: 'Spammer',
            email: 'spam@test.com',
            parentId: null,
            siteId: 1
        );

        $result = $this->detector->isSpam($dto);

        $this->assertTrue($result);
    }
}