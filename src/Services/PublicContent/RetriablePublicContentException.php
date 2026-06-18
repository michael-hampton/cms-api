<?php

namespace App\Services\PublicContent;

use App\Services\Resilience\RetriableOperationException;
use RuntimeException;

final class RetriablePublicContentException extends RuntimeException implements RetriableOperationException
{
}
