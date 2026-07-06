<?php

namespace App\Framework\Support\Config\Publishing;

use App\Framework\Support\Config\ConfigModel;

/**
 * Produces a deterministic fingerprint for a ConfigModel's *semantic*
 * content: the same set of key/value pairs always produces the same
 * fingerprint, regardless of entry ids or the order entries happen to
 * be stored in.
 *
 * Used to detect concurrent modification (Ticket 4): a fingerprint is
 * taken when a document is loaded, and the same algorithm is re-run on
 * the currently-stored document immediately before publishing. If they
 * differ, someone else changed the document in between.
 */
final class ConfigFingerprinter
{
    public function fingerprint(ConfigModel $model): string
    {
        $pairs = $model->toPairs();

        usort($pairs, static function (array $a, array $b): int {
            $keyComparison = $a[0] <=> $b[0];

            if ($keyComparison !== 0) {
                return $keyComparison;
            }

            return self::canonicalise($a[1]) <=> self::canonicalise($b[1]);
        });

        $canonical = self::canonicalise($pairs);

        return hash('sha256', $canonical);
    }

    /**
     * Recursively produces a stable, order-independent-at-the-object-level
     * JSON representation: object keys are sorted, arrays keep their
     * order (order is semantically meaningful for lists).
     */
    private static function canonicalise(mixed $value): string
    {
        if (is_array($value)) {
            $isList = array_is_list($value);

            if (!$isList) {
                ksort($value);
            }

            $parts = [];

            foreach ($value as $k => $v) {
                $parts[] = $isList
                    ? self::canonicalise($v)
                    : json_encode((string) $k) . ':' . self::canonicalise($v);
            }

            return $isList ? '[' . implode(',', $parts) . ']' : '{' . implode(',', $parts) . '}';
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}