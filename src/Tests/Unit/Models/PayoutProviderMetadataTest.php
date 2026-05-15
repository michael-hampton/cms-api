<?php

namespace App\Tests\Unit\Models;

use App\Models\Payout;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class PayoutProviderMetadataTest extends FunctionalTestCase
{
    public function test_provider_metadata_persists_and_json_casts(): void
    {
        $payout = Payout::create([
            'user_id' => 1,
            'site_id' => $this->siteId,
            'amount' => 12345,
            'currency' => 'GBP',
            'status' => 'pending',
            'method' => 'stripe',
            'provider' => 'stripe_connect',
            'provider_payout_id' => 'po_123',
            'provider_transfer_id' => 'tr_123',
            'provider_status' => 'processing',
            'provider_response_json' => ['hello' => 'world'],
            'processing_attempts' => 2,
        ]);

        $fresh = Payout::find($payout->id);

        $this->assertNotNull($fresh);
        $this->assertEquals('stripe_connect', $fresh->provider);
        $this->assertEquals('po_123', $fresh->provider_payout_id);
        $this->assertEquals('tr_123', $fresh->provider_transfer_id);
        $this->assertEquals('processing', $fresh->provider_status);
        $this->assertEquals(2, $fresh->processing_attempts);
        $this->assertEquals(['hello' => 'world'], $fresh->provider_response_json);
        $this->assertIsArray($fresh->provider_response_json);
    }
}

