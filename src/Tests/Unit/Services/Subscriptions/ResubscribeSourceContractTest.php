<?php

namespace App\Tests\Unit\Services\Subscriptions;

use PHPUnit\Framework\TestCase;

final class ResubscribeSourceContractTest extends TestCase
{
    public function test_checkout_payload_source_id_is_used_by_batch_factory(): void
    {
        $source = $this->read('Services/Subscriptions/SubscriptionBatchFactory.php');

        self::assertStringContainsString('resubscribe_from_subscription_id', $source);
        self::assertStringContainsString('tagResubscribeSource', $source);
        self::assertStringContainsString('renewed_from_subscription_id', $source);
    }

    public function test_success_listener_completes_old_to_new_link(): void
    {
        $source = $this->read('Listeners/Subscriptions/RecordSubscriptionHistoryListener.php');

        self::assertStringContainsString('finaliseResubscribeLink', $source);
        self::assertStringContainsString('replaced_by_subscription_id', $source);
    }

    private function read(string $relativePath): string
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/' . $relativePath);
        self::assertNotFalse($source);

        return $source;
    }
}
