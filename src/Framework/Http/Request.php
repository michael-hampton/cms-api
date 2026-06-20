<?php

namespace App\Framework\Http;

use App\Framework\AuthenticatedUser;
use App\Framework\Session\Session;
use App\Models\Site;
use App\Models\User;

class Request implements RequestInterface
{
    protected array $data = [];
    public array $files = [];
    private ?Site $site = null;
    private string $originalPath;
    protected array $routeParams = [];
    private array $attributes = [];
    public AuthenticatedUser|User|null $user = null;
    private $headers;
    private string $path;
    private string $rawBody;
    protected int $siteId;

    public function __construct(array $data = [], array $files = [], array $routeParams = [])
    {
        $this->routeParams = $routeParams;
        $this->rawBody = file_get_contents('php://input') ?: '';
        $this->headers = [];

        if (function_exists('getallheaders')) {
            $this->headers = getallheaders();
        } elseif (function_exists('apache_request_headers')) {
            $this->headers = apache_request_headers();
        }

        if (empty($this->headers)) {
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
        $data = $data ?: $_GET;
        $files = $files ?: [];
        $bodyData = $this->getBodyData($method, $contentType);

        $processedFiles = [];
        foreach ($files ?: $_FILES as $key => $file) {
            $processedFiles[$key] = $file instanceof UploadedFile ? $file : new UploadedFile($file);
        }

        $data = array_merge($data, $bodyData['data']);

        if (!empty($files)) {
            $data = $data ?: $_POST;
        }

        $this->files = $processedFiles ?: $bodyData['files'] ?: [];
        $this->data = $data;
    }

    private function getBodyData(string $method, string $contentType): array
    {
        if (stripos($contentType, 'application/json') !== false) {
            $json = json_decode($this->rawBody, true);

            return [
                'data' => is_array($json) ? $json : [],
                'files' => [],
            ];
        }

        if (stripos($contentType, 'multipart/form-data') !== false) {
            if ($method === 'POST') {
                return ['data' => $_POST, 'files' => $_FILES];
            }

            return $this->parseMultipartFormData($this->rawBody, $contentType);
        }

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

    public function file(string $key): ?UploadedFile
    {
        return UploadedFile::createFromGlobal($key);
    }

    public function hasFile(string $key): bool
    {
        if (isset($this->files[$key]) && $this->files[$key] instanceof UploadedFile) {
            return true;
        }

        return isset($_FILES[$key]) && is_uploaded_file($_FILES[$key]['tmp_name'] ?? '');
    }

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

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function setSite(?Site $site): void
    {
        $this->site = $site;
    }

    public function site(): ?Site
    {
        return $this->site;
    }
}
