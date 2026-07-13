<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an uploaded file fails ingest validation (wrong type, too long,
 * corrupt). Carries a user-safe message; the controller maps it to HTTP 422.
 */
class IngestValidationException extends RuntimeException
{
}
