<?php

namespace App\Tests\Unit\Services\PublicContent\Locale;

use App\Services\PublicContent\Locale\LocaleRulesArtefactLoader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LocaleRulesArtefactLoaderTest extends TestCase
{
    public function test_loads_versioned_artefact(): void
    {
        $path = dirname(__DIR__, 5) . '/config/public-content-locale-rules.json';

        $artefact = (new LocaleRulesArtefactLoader())->load($path);

        self::assertSame(1, $artefact->schemaVersion);
        self::assertNotEmpty($artefact->locales);
        self::assertNotNull($artefact->findByLocale('en-GB'));
        self::assertSame('uk', $artefact->findByLocale('en-GB')?->urlPrefix);
    }

    public function test_missing_artefact_refuses_to_load(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing');

        (new LocaleRulesArtefactLoader())->load('/tmp/does-not-exist-locale-rules.json');
    }

    public function test_malformed_artefact_refuses_to_load(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'locale-rules');
        file_put_contents($path, '{not-json');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('malformed');
            (new LocaleRulesArtefactLoader())->load($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_wrong_schema_version_refuses_to_load(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'locale-rules');
        file_put_contents($path, json_encode([
            'schema_version' => 9,
            'locales' => [
                [
                    'locale' => 'en-GB',
                    'language' => 'en',
                    'region' => 'GB',
                    'url_prefix' => 'uk',
                    'enabled' => true,
                ],
            ],
        ]));

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('wrong schema_version');
            (new LocaleRulesArtefactLoader())->load($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_loads_edge_redirect_rules(): void
    {
        $path = dirname(__DIR__, 5) . '/config/public-content-locale-rules.json';

        $artefact = (new LocaleRulesArtefactLoader())->load($path);

        self::assertTrue($artefact->edgeRedirects->collapseDoubledRegion);
        self::assertTrue($artefact->edgeRedirects->preferRegionalHome);
        self::assertSame('/', $artefact->edgeRedirects->disabledLocaleFallbackPath);
        self::assertNotNull($artefact->findByUrlPrefix('ca', enabledOnly: false));
        self::assertFalse($artefact->findByUrlPrefix('ca', enabledOnly: false)?->enabled);
    }
}
