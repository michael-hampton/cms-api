<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\Import;

use App\Services\Subscriptions\Import\SubscriptionCsvReader;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SubscriptionCsvReaderTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    public function test_reads_and_normalises_csv_rows(): void
    {
        $file = $this->csv(
            "email,first_name,last_name,plan_id,payment_method_id,address_line_1,address_line_2,city,county,postcode,country_code,phone,pricing_tier_id,offer_type\n"
            . " JANE@EXAMPLE.COM ,Jane,Doe,5,pm_test,1 High Street,,London,,SW1A 1AA,gb,,9,digital\n"
        );

        $rows = iterator_to_array((new SubscriptionCsvReader())->read($file), false);

        self::assertCount(1, $rows);
        self::assertSame(2, $rows[0]['line']);
        self::assertSame('jane@example.com', $rows[0]['row']->email);
        self::assertSame(5, $rows[0]['row']->planId);
        self::assertSame(9, $rows[0]['row']->pricingTierId);
        self::assertSame('GB', $rows[0]['row']->address['country_code']);
    }

    public function test_rejects_empty_file(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CSV file is empty.');

        iterator_to_array((new SubscriptionCsvReader())->read($this->csv('')));
    }

    public function test_rejects_malformed_row(): void
    {
        $file = $this->csv("email,first_name\njane@example.com,Jane,extra\n");

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CSV line 2 has the wrong number of columns.');

        iterator_to_array((new SubscriptionCsvReader())->read($file));
    }

    public function test_rejects_unreadable_path(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SubscriptionCsvReader())->read('/path/that/does/not/exist')->current();
    }

    private function csv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'subscription-import-');
        file_put_contents($path, $contents);
        $this->files[] = $path;
        return $path;
    }
}
