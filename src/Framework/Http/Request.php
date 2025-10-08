<?php

namespace App\Framework\Http;

use App\Framework\AuthenticatedUser;
use App\Models\User;

class Request implements RequestInterface
{
    protected array $data = [];
    public array $files = [];
    protected array $routeParams = [];
    public AuthenticatedUser|User|null $user = null;
    private $headers;

    public function __construct(array $data = [], array $files = [], array $routeParams = [])
    {
        $this->routeParams = $routeParams;
        $this->headers = [];

        if (function_exists('getallheaders')) {
            $this->headers = getallheaders();
        } elseif (function_exists('apache_request_headers')) {
            $this->headers = apache_request_headers();
        }

        if(empty($headers)) {
            foreach ($_SERVER as $key => $value) {
                if (str_starts_with($key, 'HTTP_')) {
                    $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                    $this->headers[$name] = $value;
                }
            }
        }

        $this->headers['Content-Type'] = $_SERVER['CONTENT_TYPE'] ?? '';

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        // Start with query params
        $data = $data ?: $_GET;
        $files = $files ?: [];

        // Merge body data based on content type
        $bodyData = $this->getBodyData($method, $contentType);

// Wrap files into UploadedFile
        $processedFiles = [];
        foreach ($files ?: $_FILES as $key => $file) {
            $processedFiles[$key] = $file instanceof UploadedFile ? $file : new UploadedFile($file);
        }

// Merge form/json data
        $data = array_merge($data, $bodyData['data']);

// If we have files passed in tests, keep the original $data
        if (!empty($files)) {
            $data = $data ?: $_POST;
        }

        $this->files = $processedFiles ?: $bodyData['files'] ?: [];
        $this->data = $data;
    }

    private function getBodyData(string $method, string $contentType): array
    {
        if (stripos($contentType, 'application/json') !== false) {
            $json = json_decode(file_get_contents('php://input'), true) ?: [];
            return ['data' => $json, 'files' => []];
        }

        if (stripos($contentType, 'multipart/form-data') !== false) {
            if ($method === 'POST') {
                return ['data' => $_POST, 'files' => $_FILES];
            }
            // PUT/PATCH multipart
            return $this->parseMultipartFormData(file_get_contents('php://input'), $contentType);
        }

        // Default: x-www-form-urlencoded
        if ($method !== 'GET') {
            return ['data' => $_POST, 'files' => []];
        }

        return ['data' => [], 'files' => []];
    }

    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function input(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->data, array_flip($keys));
    }

    public function route(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->routeParams;
        }
        return $this->routeParams[$key] ?? $default;
    }

    public function user(): AuthenticatedUser|User|null
    {
        return $this->user;
    }

    public function setUser(AuthenticatedUser|User|null $user): void
    {
        $this->user = $user;
    }

    public function except(array $keys): array
    {
        return array_diff_key($this->data, array_flip($keys));
    }

    public function put(string $key, mixed $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Get an uploaded file from the request
     *
     * @param string $key
     * @return UploadedFile|null
     */
    public function file(string $key): ?UploadedFile
    {
        return UploadedFile::createFromGlobal($key);
    }

    /**
     * Check if request has file
     *
     * @param string $key
     * @return bool
     */
    public function hasFile(string $key): bool
    {
        // If you already have a UploadedFile object
        if (isset($this->files[$key]) && $this->files[$key] instanceof UploadedFile) {
            return true;
        }

        // Fallback to $_FILES for real HTTP uploads
        return isset($_FILES[$key]) &&
            is_uploaded_file($_FILES[$key]['tmp_name'] ?? '');
    }


    /**
     * Get all uploaded files
     *
     * @return array
     */
    public function allFiles(): array
    {
        $files = [];
        foreach ($_FILES as $key => $fileInfo) {
            $files[$key] = new UploadedFile($fileInfo);
        }
        return $files;
    }

    public function header(string $key): ?string
    {
        return $this->headers[$key] ?? null;
    }

    public function method(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }

    public function files(): array
    {
        return $this->files;
    }

    public function setRouteParams(array $params): self
    {
        $this->routeParams = $params;

        return $this;
    }

    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    private function parseMultipartFormData(string $rawData, string $contentType): array
    {
        $data = [];
        $files = [];

        if (preg_match('/boundary=(.*)$/', $contentType, $matches)) {
            $boundary = $matches[1];

            $blocks = preg_split("/-+$boundary/", $rawData);
            array_pop($blocks); // remove trailing "--"

            foreach ($blocks as $block) {
                if (empty(trim($block))) {
                    continue;
                }

                // Headers + body split
                [$rawHeaders, $body] = explode("\r\n\r\n", $block, 2);
                $body = rtrim($body, "\r\n");

                // Parse headers
                preg_match('/name="([^"]+)"/', $rawHeaders, $nameMatch);
                $name = $nameMatch[1] ?? null;

                preg_match('/filename="([^"]+)"/', $rawHeaders, $fileMatch);
                $filename = $fileMatch[1] ?? null;

                if ($filename) {
                    preg_match('/Content-Type:\s?([^\r\n]+)/', $rawHeaders, $typeMatch);
                    $type = $typeMatch[1] ?? 'application/octet-stream';

                    $files[$name] = [
                        'name'     => $filename,
                        'type'     => $type,
                        'tmp_name' => $this->writeTempFile($body, $filename),
                        'error'    => 0,
                        'size'     => strlen($body),
                    ];
                } elseif ($name) {
                    $data[$name] = $body;
                }
            }
        }

        return [
            'data' => $data,
            'files' => $files,
        ];
    }

    private function writeTempFile(string $content, string $filename): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'upload_');
        file_put_contents($tmp, $content);
        return $tmp;
    }

    public function getHeader(string $key): ?string {
        return $this->headers[$key] ?? null;;
    }

}