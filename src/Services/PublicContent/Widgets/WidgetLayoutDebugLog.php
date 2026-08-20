<?php

namespace App\Services\PublicContent\Widgets;

use Throwable;

/**
 * Temporary debug log for widget layout / page-override investigation.
 */
final class WidgetLayoutDebugLog
{
    public static function write(string $event, array $context): void
    {
        $record = [
            'event' => $event,
            'at' => date(DATE_ATOM),
            ...$context,
        ];

        try {
            $path = dirname(__DIR__, 3) . '/storage/logs/public-content-widget-layout.jsonl';
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($path, json_encode($record) . "\n", FILE_APPEND | LOCK_EX);
        } catch (Throwable) {
            // Ignore debug I/O failures.
        }
    }
}
