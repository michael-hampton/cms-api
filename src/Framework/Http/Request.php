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
            return ['data' => is_array($json) ? $json : [], 'files' => []];
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

    public function get(string $key, $default = null) { return $this->data[$key] ?? $default; }
    public function all(): array { return $this->data; }
    public function input(string $key, $default = null) { return $this->data[$key] ?? $default; }
    public function only(array $keys): array { return array_intersect_key($this->data, array_flip($keys)); }

    public function route(?string $key = null, $default = null)
    {
        return $key === null ? $this->routeParams : ($this->routeParams[$key] ?? $default);
    }

    public function user(): AuthenticatedUser|User|null { return $this->user; }
    public function setUser(AuthenticatedUser|User|null $user): void { $this->user = $user; }
    public function except(array $keys): array { return array_diff_key($this->data, array_flip($keys)); }

    public function put(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function has(string $key): bool { return array_key_exists($key, $this->data); }
    public function file(string $key): ?UploadedFile { return UploadedFile::createFromGlobal($key); }

    public function hasFile(string $key): bool
    {
        return (isset($this->files[$key]) && $this->files[$key] instanceof UploadedFile)
            || (isset($_FILES[$key]) && is_uploaded_file($_FILES[$key]['tmp_name'] ?? ''));
    }

    public function allFiles(): array
    {
        $files = [];
        foreach ($_FILES as $key => $fileInfo) {
            $files[$key] = new UploadedFile($fileInfo);
        }
        return $files;
    }

    public function header(string $key): ?string { return $this->headers[$key] ?? null; }
    public function method(): string { return $_SERVER['REQUEST_METHOD']; }
    public function isPost(): bool { return $this->method() === 'POST'; }
    public function isGet(): bool { return $this->method() === 'GET'; }
    public function files(): array { return $this->files; }

    public function setRouteParams(array $params): self
    {
        $this->routeParams = $params;
        return $this;
    }

    public function getRouteParams(): array { return $this->routeParams; }

    private function parseMultipartFormData(string $rawData, string $contentType): array
    {
        $data = [];
        $files = [];

        if (preg_match('/boundary=(.*)$/', $contentType, $matches)) {
            $boundary = $matches[1];
            $blocks = preg_split("/-+$boundary/", $rawData);
            array_pop($blocks);

            foreach ($blocks as $block) {
                if (empty(trim($block))) {
                    continue;
                }

                [$rawHeaders, $body] = explode("\r\n\r\n", $block, 2);
                $body = rtrim($body, "\r\n");
                preg_match('/name="([^"]+)"/', $rawHeaders, $nameMatch);
                $name = $nameMatch[1] ?? null;
                preg_match('/filename="([^"]+)"/', $rawHeaders, $fileMatch);
                $filename = $fileMatch[1] ?? null;

                if ($filename) {
                    preg_match('/Content-Type:\s?([^\r\n]+)/', $rawHeaders, $typeMatch);
                    $files[$name] = [
                        'name' => $filename,
                        'type' => $typeMatch[1] ?? 'application/octet-stream',
                        'tmp_name' => $this->writeTempFile($body, $filename),
                        'error' => 0,
                        'size' => strlen($body),
                    ];
                } elseif ($name) {
                    $data[$name] = $body;
                }
            }
        }

        return ['data' => $data, 'files' => $files];
    }

    private function writeTempFile(string $content, string $filename): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'upload_');
        file_put_contents($tmp, $content);
        return $tmp;
    }

    public function getHeader(string $key): ?string { return $this->headers[$key] ?? null; }

    public function setSite(Site $site): void
    {
        $this->site = $site;
        $this->siteId = $site->id;
    }

    public function getSite(): ?Site { return $this->site; }
    public function setSiteId(int $siteId): void { $this->siteId = $siteId; }
    public function getSiteId(): ?int { return $this->siteId ?? null; }
    public function getHost(): string { return $_SERVER['HTTP_HOST'] ?? 'localhost'; }

    public function getPath(): string
    {
        return isset($this->path) ? $this->path : parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    }

    public function setPath(string $path): void
    {
        if (!isset($this->originalPath)) {
            $this->originalPath = $this->getPath();
        }
        $this->path = $path;
    }

    public function getOriginalPath(): string { return $this->originalPath ?? $this->getPath(); }

    public function getFullUrl(): string
    {
        return $this->getScheme() . '://' . $this->getHost() . ($_SERVER['REQUEST_URI'] ?? '/');
    }

    public function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    }

    public function getScheme(): string { return $this->isSecure() ? 'https' : 'http'; }

    private static function isLocalhost(string $host): bool
    {
        $host = strtok($host, ':');
        return in_array($host, ['localhost', '127.0.0.1', '::1']);
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->input($key, $default);
        if (in_array($value, [true, 'true', 1, '1', 'on', 'yes', 'y'], true)) {
            return true;
        }
        if (in_array($value, [false, 'false', 0, '0', 'off', 'no', 'n'], true)) {
            return false;
        }
        return $default;
    }

    public function merge(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->data[$key] = $value;
        }
    }

    public function getUri(): string { return $_SERVER['REQUEST_URI'] ?? '/'; }

    public function query(?string $key = null, $default = null)
    {
        return $key === null ? $_GET : ($_GET[$key] ?? $default);
    }

    public function session(): Session { return new Session(); }

    public function setAttribute(string $key, $value): self
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    public function getAttribute(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    public function hasAttribute(string $key): bool { return array_key_exists($key, $this->attributes); }

    public function old(string $key, $default = null)
    {
        $oldInput = Session::getFlash('old_input', []);
        return $oldInput[$key] ?? $default;
    }

    public function getHeaders(): false|array { return $this->headers; }

    public function ip(): ?string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
            $ip = $_SERVER['HTTP_FORWARDED'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            return null;
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ?: null;
    }

    public function userAgent(): ?string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? $this->header('User-Agent') ?? null;
    }

    public function wantsJson()
    {
        return $this->getHeader('X-Requested-With') === 'XMLHttpRequest'
            || $this->getHeader('Content-Type') === 'application/json'
            || str_contains($this->getHeader('Accept') ?? '', 'application/json');
    }

    public function getContent(): string { return $this->rawBody; }
}
