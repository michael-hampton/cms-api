<?php

namespace App\Framework\Support;

class Str
{
    /**
     * Convert a string to a URL-friendly slug
     */
    public static function slug(
        string $title,
        ?callable $existsCallback = null,
        string $separator = '-',
        string $language = 'en'
    ): string {
        $title = static::ascii($title, $language);

        // Convert all dashes/underscores into separator
        $flip = $separator === '-' ? '_' : '-';
        $title = preg_replace('![' . preg_quote($flip) . ']+!u', $separator, $title);

        // Replace @ with the word 'at'
        $title = str_replace('@', $separator . 'at' . $separator, $title);

        // Remove all characters that are not the separator, letters, numbers, or whitespace
        $title = preg_replace('![^' . preg_quote($separator) . '\pL\pN\s]+!u', '', $title);

        // Replace all separator characters and whitespace by a single separator
        $title = preg_replace('![' . preg_quote($separator) . '\s]+!u', $separator, $title);

        $slug = trim($title, $separator);
        $slug = mb_strtolower($slug, 'UTF-8'); // <-- ensures lowercase

        return $existsCallback ? static::ensureUniqueSlug($slug, $existsCallback) : $slug;
    }

    private static function ensureUniqueSlug(string $baseSlug, callable $existsCallback): string
    {
        $slug = $baseSlug;
        $counter = 1;

        while ($existsCallback($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Transliterate a UTF-8 value to ASCII
     */
    public static function ascii(string $value, string $language = 'en'): string
    {
        $languageSpecific = static::charsArray()[$language] ?? null;

        if ($languageSpecific) {
            $value = str_replace($languageSpecific[0], $languageSpecific[1], $value);
        }

        return iconv('UTF-8', 'ASCII//TRANSLIT', $value);
    }

    /**
     * Convert a value to camel case
     */
    public static function camel(string $value): string
    {
        return lcfirst(static::studly($value));
    }

    /**
     * Convert a value to studly caps case
     */
    public static function studly(string $value): string
    {
        $value = ucwords(str_replace(['-', '_'], ' ', $value));
        return str_replace(' ', '', $value);
    }

    /**
     * Convert a string to snake_case
     */
    public static function snake(string $value, string $delimiter = '_'): string
    {
        if (!ctype_lower($value)) {
            $value = preg_replace('/\s+/u', '', ucwords($value));
            $value = strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value));
        }
        return $value;
    }

    /**
     * Convert a string to kebab-case
     */
    public static function kebab(string $value): string
    {
        return static::snake($value, '-');
    }

    /**
     * Convert a string to Title Case
     */
    public static function title(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Generate a random string
     */
    public static function random(int $length = 16): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $result;
    }

    /**
     * Determine if a given string contains a given substring
     */
    public static function contains(string $haystack, $needles): bool
    {
        $needles = (array) $needles;
        foreach ($needles as $needle) {
            if ($needle !== '' && mb_strpos($haystack, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Determine if a given string starts with a given substring
     */
    public static function startsWith(string $haystack, $needles): bool
    {
        $needles = (array) $needles;
        foreach ($needles as $needle) {
            if ($needle !== '' && substr($haystack, 0, strlen($needle)) === (string) $needle) {
                return true;
            }
        }
        return false;
    }

    /**
     * Determine if a given string ends with a given substring
     */
    public static function endsWith(string $haystack, $needles): bool
    {
        $needles = (array) $needles;
        foreach ($needles as $needle) {
            if (substr($haystack, -strlen($needle)) === (string) $needle) {
                return true;
            }
        }
        return false;
    }

    /**
     * Limit the number of characters in a string
     */
    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strwidth($value, 'UTF-8') <= $limit) {
            return $value;
        }
        return rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')) . $end;
    }

    /**
     * Get language specific character replacements
     */
    private static function charsArray(): array
    {
        return [
            'en' => [
                ['À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ð', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ'],
                ['A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'd', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y']
            ]
        ];
    }

    public static function excerpt(string $text, int $words = 50): string
    {
        $wordArray = explode(' ', $text);

        if (count($wordArray) <= $words) {
            return $text;
        }

        return implode(' ', array_slice($wordArray, 0, $words)) . '...';
    }
}