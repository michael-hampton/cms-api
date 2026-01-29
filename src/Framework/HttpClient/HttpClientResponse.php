<?php

namespace App\Framework\HttpClient;

use App\Framework\Support\Collection;

class HttpClientResponse
{
    protected int $statusCode;
    protected array $headers;
    protected string $body;

    public function __construct(int $statusCode = 200, array $headers = [], string $body = '')
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
    }

    /**
     * Get response status code
     *
     * @return int
     */
    public function status(): int
    {
        return $this->statusCode;
    }

    /**
     * Alias for status()
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->status();
    }

    /**
     * Get response body
     *
     * @return string
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Alias for body()
     *
     * @return string
     */
    public function getBody(): string
    {
        return $this->body();
    }

    /**
     * Get response headers
     *
     * @return array
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Alias for headers()
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers();
    }

    /**
     * Get a specific header
     *
     * @param string $name
     * @return string|null
     */
    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * Alias for header()
     *
     * @param string $name
     * @return string|null
     */
    public function getHeader(string $name): ?string
    {
        return $this->header($name);
    }

    /**
     * Check if response is successful (2xx)
     *
     * @return bool
     */
    public function successful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Check if response is a redirect (3xx)
     *
     * @return bool
     */
    public function redirect(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    /**
     * Check if response failed (4xx or 5xx)
     *
     * @return bool
     */
    public function failed(): bool
    {
        return $this->serverError() || $this->clientError();
    }

    /**
     * Check if response is a client error (4xx)
     *
     * @return bool
     */
    public function clientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Check if response is a server error (5xx)
     *
     * @return bool
     */
    public function serverError(): bool
    {
        return $this->statusCode >= 500;
    }

    /**
     * Check if status code is 200
     *
     * @return bool
     */
    public function ok(): bool
    {
        return $this->statusCode === 200;
    }

    /**
     * Check if status code is 201
     *
     * @return bool
     */
    public function created(): bool
    {
        return $this->statusCode === 201;
    }

    /**
     * Check if status code is 202
     *
     * @return bool
     */
    public function accepted(): bool
    {
        return $this->statusCode === 202;
    }

    /**
     * Check if status code is 204
     *
     * @return bool
     */
    public function noContent(): bool
    {
        return $this->statusCode === 204;
    }

    /**
     * Check if status code is 301
     *
     * @return bool
     */
    public function movedPermanently(): bool
    {
        return $this->statusCode === 301;
    }

    /**
     * Check if status code is 302
     *
     * @return bool
     */
    public function found(): bool
    {
        return $this->statusCode === 302;
    }

    /**
     * Check if status code is 400
     *
     * @return bool
     */
    public function badRequest(): bool
    {
        return $this->statusCode === 400;
    }

    /**
     * Check if status code is 401
     *
     * @return bool
     */
    public function unauthorized(): bool
    {
        return $this->statusCode === 401;
    }

    /**
     * Check if status code is 402
     *
     * @return bool
     */
    public function paymentRequired(): bool
    {
        return $this->statusCode === 402;
    }

    /**
     * Check if status code is 403
     *
     * @return bool
     */
    public function forbidden(): bool
    {
        return $this->statusCode === 403;
    }

    /**
     * Check if status code is 404
     *
     * @return bool
     */
    public function notFound(): bool
    {
        return $this->statusCode === 404;
    }

    /**
     * Check if status code is 408
     *
     * @return bool
     */
    public function requestTimeout(): bool
    {
        return $this->statusCode === 408;
    }

    /**
     * Check if status code is 409
     *
     * @return bool
     */
    public function conflict(): bool
    {
        return $this->statusCode === 409;
    }

    /**
     * Check if status code is 422
     *
     * @return bool
     */
    public function unprocessableEntity(): bool
    {
        return $this->statusCode === 422;
    }

    /**
     * Check if status code is 429
     *
     * @return bool
     */
    public function tooManyRequests(): bool
    {
        return $this->statusCode === 429;
    }

    /**
     * Get response body as JSON
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function json(?string $key = null, $default = null)
    {
        $data = json_decode($this->body, true);

        if ($key === null) {
            return $data;
        }

        return $this->getNestedValue($data, $key, $default);
    }

    /**
     * Get response body as object
     *
     * @return object|null
     */
    public function object(): ?object
    {
        return json_decode($this->body);
    }

    /**
     * Get response body as Collection
     *
     * @param string|null $key
     * @return Collection
     */
    public function collect(?string $key = null): Collection
    {
        $data = $this->json($key);

        return new Collection(is_array($data) ? $data : []);
    }

    /**
     * Get response body as resource (file pointer)
     *
     * @return resource
     */
    public function resource()
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, $this->body);
        rewind($resource);
        return $resource;
    }

    /**
     * Get nested value from array using dot notation
     *
     * @param array|null $data
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getNestedValue(?array $data, string $key, $default = null)
    {
        if ($data === null) {
            return $default;
        }

        if (isset($data[$key])) {
            return $data[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (is_array($data) && array_key_exists($segment, $data)) {
                $data = $data[$segment];
            } else {
                return $default;
            }
        }

        return $data;
    }

    /**
     * Get response body as array (alias for json)
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->json() ?? [];
    }

    /**
     * Determine if the response is a redirect
     * Alias for redirect()
     *
     * @return bool
     */
    public function isRedirect(): bool
    {
        return $this->redirect();
    }

    /**
     * Determine if the response was successful
     * Alias for successful()
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->successful();
    }

    /**
     * Determine if the response indicates a client error
     * Alias for clientError()
     *
     * @return bool
     */
    public function isClientError(): bool
    {
        return $this->clientError();
    }

    /**
     * Determine if the response indicates a server error
     * Alias for serverError()
     *
     * @return bool
     */
    public function isServerError(): bool
    {
        return $this->serverError();
    }

    /**
     * Convert response to string
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->body;
    }
}