<?php

namespace App\Framework\HttpClient;

use Exception;

class HttpClient implements HttpClientInterface
{
    protected array $defaultOptions = [];
    protected array $defaultHeaders = [];
    protected ?int $timeout = null;
    protected int $retryTimes = 0;
    protected int $retryDelay = 100;

    /**
     * Perform a request
     *
     * @param string $method
     * @param string $url
     * @param array $options
     * @return HttpClientResponse
     * @throws HttpClientException
     */
    protected function request(string $method, string $url, array $options = []): HttpClientResponse
    {
        $ch = curl_init();

        // Merge options
        $options = array_merge($this->defaultOptions, $options);

        // Merge headers
        $headers = array_merge($this->defaultHeaders, $options['headers'] ?? []);

        // Set timeout
        $timeout = $options['timeout'] ?? $this->timeout ?? 30;

        // Build curl options
        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => $options['follow_redirects'] ?? true,
            CURLOPT_MAXREDIRS => $options['max_redirects'] ?? 5,
            CURLOPT_SSL_VERIFYPEER => $options['verify'] ?? true,
            CURLOPT_SSL_VERIFYHOST => $options['verify'] ?? true ? 2 : 0,
        ];

        // Set method-specific options
        switch (strtoupper($method)) {
            case 'POST':
                $curlOptions[CURLOPT_POST] = true;
                $curlOptions[CURLOPT_POSTFIELDS] = $this->prepareBody($options);
                break;
            case 'PUT':
            case 'PATCH':
            case 'DELETE':
                $curlOptions[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
                if (isset($options['body']) || isset($options['json']) || isset($options['form_params'])) {
                    $curlOptions[CURLOPT_POSTFIELDS] = $this->prepareBody($options);
                }
                break;
            case 'HEAD':
                $curlOptions[CURLOPT_NOBODY] = true;
                break;
        }

        // Set headers
        if (!empty($headers)) {
            $curlOptions[CURLOPT_HTTPHEADER] = $this->formatHeaders($headers);
        }

        // Apply curl options
        curl_setopt_array($ch, $curlOptions);

        // Execute with retry logic
        $attempt = 0;
        $maxAttempts = $this->retryTimes + 1;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            try {
                $response = curl_exec($ch);

                if ($response === false) {
                    throw new HttpClientException(
                        'cURL error: ' . curl_error($ch),
                        curl_errno($ch)
                    );
                }

                $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $headerString = substr($response, 0, $headerSize);
                $body = substr($response, $headerSize);

                curl_close($ch);

                return new HttpClientResponse(
                    $statusCode,
                    $this->parseHeaders($headerString),
                    $body
                );
            } catch (Exception $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt < $maxAttempts) {
                    usleep($this->retryDelay * 1000);
                }
            }
        }

        curl_close($ch);
        throw new HttpClientException(
            'Request failed after ' . $maxAttempts . ' attempts: ' . $lastException->getMessage(),
            0,
            $lastException
        );
    }

    /**
     * Prepare request body
     *
     * @param array $options
     * @return string|array
     */
    protected function prepareBody(array $options)
    {
        if (isset($options['json'])) {
            $this->defaultHeaders['Content-Type'] = 'application/json';
            return json_encode($options['json']);
        }

        if (isset($options['form_params'])) {
            $this->defaultHeaders['Content-Type'] = 'application/x-www-form-urlencoded';
            return http_build_query($options['form_params']);
        }

        if (isset($options['multipart'])) {
            return $options['multipart'];
        }

        if (isset($options['body'])) {
            return $options['body'];
        }

        return '';
    }

    /**
     * Format headers for cURL
     *
     * @param array $headers
     * @return array
     */
    protected function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $key => $value) {
            $formatted[] = $key . ': ' . $value;
        }
        return $formatted;
    }

    /**
     * Parse response headers
     *
     * @param string $headerString
     * @return array
     */
    protected function parseHeaders(string $headerString): array
    {
        $headers = [];
        $lines = explode("\r\n", $headerString);

        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }

        return $headers;
    }

    public function get(string $url, array $options = []): HttpClientResponse
    {
        return $this->request('GET', $url, $options);
    }

    public function post(string $url, array $options = []): HttpClientResponse
    {
        return $this->request('POST', $url, $options);
    }

    public function put(string $url, array $options = []): HttpClientResponse
    {
        return $this->request('PUT', $url, $options);
    }

    public function patch(string $url, array $options = []): HttpClientResponse
    {
        return $this->request('PATCH', $url, $options);
    }

    public function delete(string $url, array $options = []): HttpClientResponse
    {
        return $this->request('DELETE', $url, $options);
    }

    public function head(string $url, array $options = []): HttpClientResponse
    {
        return $this->request('HEAD', $url, $options);
    }

    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    public function retry(int $times, int $delay = 100): self
    {
        $this->retryTimes = $times;
        $this->retryDelay = $delay;
        return $this;
    }

    public function withHeaders(array $headers): self
    {
        $this->defaultHeaders = array_merge($this->defaultHeaders, $headers);
        return $this;
    }

    public function withToken(string $token): self
    {
        $this->defaultHeaders['Authorization'] = 'Bearer ' . $token;
        return $this;
    }

    public function withBasicAuth(string $username, string $password): self
    {
        $this->defaultHeaders['Authorization'] = 'Basic ' . base64_encode($username . ':' . $password);
        return $this;
    }
}