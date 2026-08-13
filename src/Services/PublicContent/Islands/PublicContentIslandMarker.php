<?php

namespace App\Services\PublicContent\Islands;

/**
 * Single definition of the reserved island placeholder element.
 * Owned by Public Content (not a framework-internal format) so render and
 * parity stripping stay in step.
 */
final class PublicContentIslandMarker
{
    public const string ELEMENT = 'public-content-island';

    public const string ID_ATTRIBUTE = 'data-island-id';

    /** Legacy attribute names stripped by HtmlParityNormaliser. */
    public const array PARITY_ATTRIBUTES = [
        'data-pods-only-island-marker',
        'data-pods-island-marker',
        'data-parity-pods-only-marker',
    ];

    public static function placeholder(string $islandId): string
    {
        $id = htmlspecialchars($islandId, ENT_QUOTES, 'UTF-8');

        return sprintf('<%s %s="%s"></%s>', self::ELEMENT, self::ID_ATTRIBUTE, $id, self::ELEMENT);
    }

    public static function xpathQuery(): string
    {
        return '//' . self::ELEMENT . '[@' . self::ID_ATTRIBUTE . ']';
    }
}
