<?php

namespace App\Tests\Unit\Services\PublicContent\Config;

use App\Services\PublicContent\Config\DesignTokensArtefactLoader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DesignTokensArtefactLoaderTest extends TestCase
{
    public function test_loads_versioned_artefact(): void
    {
        $path = dirname(__DIR__, 5) . '/config/public-content-design-tokens.json';

        $artefact = (new DesignTokensArtefactLoader())->load($path);

        self::assertSame(1, $artefact->schemaVersion);
        self::assertSame('design-tokens-v1', $artefact->artefactVersion);
        self::assertNotEmpty($artefact->defaults);
        self::assertArrayHasKey('color', $artefact->defaults);
        self::assertSame(1, $artefact->envelope()->schemaVersion);
    }

    public function test_missing_artefact_refuses_to_load(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing');

        (new DesignTokensArtefactLoader())->load('/tmp/does-not-exist-design-tokens.json');
    }

    public function test_malformed_artefact_refuses_to_load(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'design-tokens');
        file_put_contents($path, '{not-json');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('malformed');
            (new DesignTokensArtefactLoader())->load($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_wrong_schema_version_refuses_to_load(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'design-tokens');
        file_put_contents($path, json_encode([
            'schema_version' => 99,
            'artefact_version' => 'x',
            'defaults' => ['color' => ['primary' => '#000']],
        ]));

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('wrong schema_version');
            (new DesignTokensArtefactLoader())->load($path);
        } finally {
            @unlink($path);
        }
    }
}
