<?php

namespace App\Services\Scoring;

use RuntimeException;

/**
 * Thrown when the LLM's output can't be coerced into the strict highlight
 * schema. The scoring job treats this as a normal failure (retry / mark
 * failed) rather than trusting partial output.
 */
class InvalidHighlightSchemaException extends RuntimeException
{
}
