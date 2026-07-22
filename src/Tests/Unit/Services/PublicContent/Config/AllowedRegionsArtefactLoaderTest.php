<?php

namespace App\Tests\Unit\Services\PublicContent\Config;

use App\Services\PublicContent\Config\AllowedRegionsArtefactLoader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AllowedRegionsArtefactLoaderTest extends TestCase
{
    public function test_loads_versioned_artefact(): void
    {
        $path = dirname(__DIR__, 5) . '/config/public-content-allowed-regions.json';

        $artefact = (new AllowedRegionsArtefactLoader())->load($path);

        self::assertSame(1, $artefact->schemaVersion);
        self::assertSame('allowed-regions-v1', $artefact->artefactVersion);
        self::assertTrue($artefact->allows('GB'));
        self::assertTrue($artefact->allows('us'));
        self::assertFalse($artefact->allows('ZZ'));
    }

    public function test_missing_artefact_refuses_to_load(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing');

        (new AllowedRegionsArtefactLoader())->load('/tmp/does-not-exist-allowed-regions.json');
    }

    public function test_malformed_artefact_refuses_to_load(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'allowed-regions');
        file_put_contents($path, '{not-json');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('malformed');
            (new AllowedRegionsArtefactLoader())->load($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_wrong_schema_version_refuses_to_load(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'allowed-regions');
        file_put_contents($path, json_encode([
            'schema_version' => 2,
            'artefact_version' => 'x',
            'regions' => ['GB'],
        ]));

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('wrong schema_version');
            (new AllowedRegionsArtefactLoader())->load($path);
        } finally {
            @unlink($path);
        }
    }
}
