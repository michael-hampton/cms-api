<?php

namespace App\Framework\Support\Config\Json;

use RuntimeException;

/**
 * Raised when raw text is not syntactically valid JSON.
 *
 * Kept deliberately distinct from configuration validation errors (see
 * ConfigValidationResult) so callers — and ultimately the UI — can tell
 * "this isn't JSON at all" apart from "this is valid JSON but not a
 * valid configuration" (Ticket 2's core requirement).
 */
final class JsonSyntaxException extends RuntimeException
{
}