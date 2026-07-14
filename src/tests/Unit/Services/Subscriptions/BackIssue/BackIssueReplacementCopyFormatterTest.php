<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\BackIssue;

use App\Models\SubscriptionIssueFulfilment;
use App\Services\Subscriptions\BackIssue\BackIssueReplacementCopyFormatter;
use Mockery;
use PHPUnit\Framework\TestCase;

class BackIssueReplacementCopyFormatterTest extends TestCase
{
    private BackIssueReplacementCopyFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new BackIssueReplacementCopyFormatter();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_format_writes_a_header_row(): void
    {
        $csv = $this->formatter->format([]);

        $this->assertStringContainsString('fulfilment_id,subscription_id,issue_delivery_id', $csv);
    }

    public function test_format_writes_one_row_per_fulfilment(): void
    {
        $fulfilments = [
            $this->makeFulfilment(1, 10, 20),
            $this->makeFulfilment(2, 11, 21),
        ];

        $csv = $this->formatter->format($fulfilments);
        $lines = array_values(array_filter(explode("\n", str_replace("\r\n", "\n", $csv))));

        $this->assertCount(3, $lines); // header + 2 rows
        $this->assertSame('1,10,20', $lines[1]);
        $this->assertSame('2,11,21', $lines[2]);
    }

    public function test_extension_is_csv(): void
    {
        $this->assertSame('csv', $this->formatter->extension());
    }

    private function makeFulfilment(int $id, int $subscriptionId, int $issueDeliveryId): SubscriptionIssueFulfilment
    {
        $fulfilment = Mockery::mock(SubscriptionIssueFulfilment::class)->makePartial();
        $fulfilment->id = $id;
        $fulfilment->subscription_id = $subscriptionId;
        $fulfilment->issue_delivery_id = $issueDeliveryId;

        return $fulfilment;
    }
}
