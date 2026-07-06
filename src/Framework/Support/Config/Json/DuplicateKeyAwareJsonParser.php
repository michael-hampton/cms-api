<?php

namespace App\Framework\Support\Config\Json;

/**
 * A minimal, duplicate-key-preserving parser for flat JSON *objects*.
 *
 * PHP's json_decode() (like JS's JSON.parse()) silently collapses
 * duplicate keys, keeping only the last one. That is exactly the
 * behaviour Ticket 2 requires us to avoid: duplicate keys must be
 * *detected and surfaced*, not swallowed before the config model ever
 * sees them.
 *
 * This class only needs to understand the top level of the document
 * (the object's own keys) — values themselves are parsed with the
 * normal json_decode(), since duplicate detection inside nested
 * structures is out of scope for a flat key/value config document.
 */
final class DuplicateKeyAwareJsonParser
{
    /**
     * Parses a JSON object into an ordered list of [key, value] pairs,
     * preserving duplicate keys exactly as they appear in the source
     * text (so the result can be fed straight into
     * ConfigModel::fromPairs()).
     *
     * @return list<array{0: string, 1: mixed}>
     * @throws JsonSyntaxException on malformed JSON or a non-object root
     */
    public static function parseObjectPairs(string $json): array
    {
        $trimmed = trim($json);

        if ($trimmed === '') {
            throw new JsonSyntaxException('Empty input is not a valid JSON object');
        }

        if ($trimmed[0] !== '{' || $trimmed[-1] !== '}') {
            throw new JsonSyntaxException('Configuration JSON must be a top-level object ({ ... })');
        }

        $entries = self::splitTopLevelEntries($trimmed);

        $pairs = [];

        foreach ($entries as $entry) {
            $pairs[] = self::parseEntry($entry);
        }

        return $pairs;
    }

    /**
     * Splits the inside of a top-level `{ ... }` object into its raw
     * `"key": value` entry strings, without being fooled by commas or
     * braces that appear inside nested values or strings.
     *
     * @return list<string>
     */
    private static function splitTopLevelEntries(string $json): array
    {
        $inner = substr($json, 1, -1);
        $length = strlen($inner);

        $entries = [];
        $depth = 0;
        $inString = false;
        $escaped = false;
        $current = '';
        $expectingEntry = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $inner[$i];

            if ($inString) {
                $current .= $char;

                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
                $current .= $char;
                continue;
            }

            if ($char === '{' || $char === '[') {
                $depth++;
                $current .= $char;
                continue;
            }

            if ($char === '}' || $char === ']') {
                $depth--;

                if ($depth < 0) {
                    throw new JsonSyntaxException('Unbalanced brackets in configuration JSON');
                }

                $current .= $char;
                continue;
            }

            if ($char === ',' && $depth === 0) {
                $trimmed = trim($current);

                if ($trimmed === '') {
                    throw new JsonSyntaxException('Unexpected comma in configuration JSON');
                }

                $entries[] = $current;
                $current = '';
                $expectingEntry = true;
                continue;
            }

            $current .= $char;
        }

        if ($inString) {
            throw new JsonSyntaxException('Unterminated string in configuration JSON');
        }

        if ($depth !== 0) {
            throw new JsonSyntaxException('Unbalanced brackets in configuration JSON');
        }

        $trimmed = trim($current);

        if ($trimmed !== '') {
            $entries[] = $current;
        } elseif ($expectingEntry) {
            throw new JsonSyntaxException('Trailing comma in configuration JSON');
        }

        return $entries;
    }

    /**
     * @return array{0: string, 1: mixed}
     */
    private static function parseEntry(string $entry): array
    {
        $entry = trim($entry);

        if (!preg_match('/^"((?:[^"\\\\]|\\\\.)*)"\s*:\s*(.*)$/s', $entry, $matches)) {
            throw new JsonSyntaxException(sprintf('Malformed key/value entry: %s', self::truncate($entry)));
        }

        [, $rawKey, $rawValue] = $matches;

        $key = json_decode('"' . $rawKey . '"', flags: JSON_THROW_ON_ERROR);

        $rawValue = trim($rawValue);

        if ($rawValue === '') {
            throw new JsonSyntaxException(sprintf('Missing value for key "%s"', $key));
        }

        try {
            $value = json_decode($rawValue, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new JsonSyntaxException(sprintf('Invalid value for key "%s": %s', $key, $e->getMessage()));
        }

        return [$key, $value];
    }

    private static function truncate(string $value, int $length = 60): string
    {
        return strlen($value) > $length ? substr($value, 0, $length) . '…' : $value;
    }
}