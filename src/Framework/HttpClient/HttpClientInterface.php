<?php

namespace App\Framework\HttpClient;

interface HttpClientInterface
{
    /**
     * Perform a GET request
     *
     * @param string $url
     * @param array $options
     * @return HttpClientResponse
     * @throws HttpClientException
     */
    public function get(string $url, array $options = []): HttpClientResponse;

    /**
     * Perform a POST request
     *
     * @param string $url
     * @param array $options
     * @return HttpClientResponse
     * @throws HttpClientException
     */
    public function post(string $url, array $options = []): HttpClientResponse;

    /**
     * Perform a PUT request
     *
     * @param string $url
     * @param array $options
     * @return HttpClientResponse
     * @throws HttpClientException
     */
    public function put(string $url, array $options = []): HttpClientResponse;

    /**
     * Perform a PATCH request
     *
     * @param string $url
     * @param array $options
     * @return HttpClientResponse
     * @throws HttpClientException
     */
    public function patch(string $url, array $options = []): HttpClientResponse;

    /**
     * Perform a DELETE request
     *
     * @param string $url
     * @param array $options
     * @return HttpClientResponse
     * @throws HttpClientException
     */
    public function delete(string $url, array $options = []): HttpClientResponse;

    /**
     * Perform a HEAD request
     *
     * @param string $url
     * @param array $options
     * @return HttpClientResponse
     * @throws HttpClientException
     */
    public function head(string $url, array $options = []): HttpClientResponse;

    /**
     * Set default timeout for requests
     *
     * @param int $seconds
     * @return self
     */
    public function timeout(int $seconds): self;

    /**
     * Set retry configuration
     *
     * @param int $times
     * @param int $delay Delay in milliseconds
     * @return self
     */
    public function retry(int $times, int $delay = 100): self;

    /**
     * Set default headers
     *
     * @param array $headers
     * @return self
     */
    public function withHeaders(array $headers): self;

    /**
     * Set bearer token
     *
     * @param string $token
     * @return self
     */
    public function withToken(string $token): self;

    /**
     * Set basic authentication
     *
     * @param string $username
     * @param string $password
     * @return self
     */
    public function withBasicAuth(string $username, string $password): self;
}