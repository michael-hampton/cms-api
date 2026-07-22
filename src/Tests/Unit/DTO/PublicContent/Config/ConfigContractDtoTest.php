<?php

namespace App\Tests\Unit\DTO\PublicContent\Config;

use App\DTO\PublicContent\Config\ConfigArtefactEnvelope;
use App\DTO\PublicContent\Config\ConfigBridgeRequest;
use App\DTO\PublicContent\Config\ConfigBridgeResponse;
use App\DTO\PublicContent\Config\ConfigSnapshot;
use PHPUnit\Framework\TestCase;

final class ConfigContractDtoTest extends TestCase
{
    public function test_artefact_envelope_shape(): void
    {
        $envelope = new ConfigArtefactEnvelope(1, 'tokens-v1', ['defaults' => ['a' => 1]]);

        self::assertSame([
            'schema_version' => 1,
            'artefact_version' => 'tokens-v1',
            'payload' => ['defaults' => ['a' => 1]],
        ], $envelope->toArray());
    }

    public function test_snapshot_from_values_includes_stable_hash(): void
    {
        $snapshot = ConfigSnapshot::fromValues(1, 7, ['widgets.social' => true]);

        self::assertSame(1, $snapshot->schemaVersion);
        self::assertSame(7, $snapshot->siteId);
        self::assertNotNull($snapshot->valuesHash);
        self::assertSame(ConfigSnapshot::hashValues(['widgets.social' => true]), $snapshot->valuesHash);
    }

    public function test_bridge_request_and_response_mirror_content_bridge_style(): void
    {
        $snapshot = ConfigSnapshot::fromValues(1, 3, ['layout.default_template' => 'article']);
        $request = new ConfigBridgeRequest(3, ['layout.default_template'], $snapshot);
        $response = new ConfigBridgeResponse(3, ['layout.default_template' => 'article'], $snapshot);

        self::assertSame(3, $request->toArray()['site_id']);
        self::assertSame(['layout.default_template'], $request->toArray()['keys']);
        self::assertSame($snapshot->toArray(), $request->toArray()['snapshot']);
        self::assertSame($snapshot->toArray(), $response->toArray()['snapshot']);
        self::assertSame(['layout.default_template' => 'article'], $response->toArray()['values']);
    }
}
