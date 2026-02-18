<?php

namespace App\Imports;

use RuntimeException;

/**
 * Thrown when a single CSV row should be skipped and logged,
 * but the rest of the import should continue.
 */
final class SkippableRowException extends RuntimeException
{
}