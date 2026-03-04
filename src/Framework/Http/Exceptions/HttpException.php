<?php

namespace App\Framework\Http\Exceptions;

use Exception;

class HttpException extends Exception
{
    protected int $statusCode;
    protected array $headers;

    public function __construct(
        int    $statusCode,
        string $message = '',
        array  $headers = []
    )
    {
        parent::__construct($message ?: self::defaultMessage($statusCode));
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    protected static function defaultMessage(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            422 => 'Unprocessable Entity',
            500 => 'Internal Server Error',
            default => 'Error',
        };
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}