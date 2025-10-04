<?php

namespace App\Framework\Http;

interface RequestInterface
{
    public function get(string $key, $default = null);
    public function all(): array;
    public function only(array $keys): array;
    public function except(array $keys): array;
    public function has(string $key): bool;
    public function file(string $key): ?UploadedFile;
    public function header(string $key): ?string;
    public function method(): string;
    public function isPost(): bool;
    public function isGet(): bool;
}