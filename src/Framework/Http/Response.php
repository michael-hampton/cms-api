<?php

namespace App\Framework\Http;

use App\Framework\Container;
use App\Framework\View\ViewRenderer;
use Exception;

class Response
{
    private $content;
    private $statusCode;
    private $headers;

    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public function send(): void
    {
        // Set status code
        http_response_code($this->statusCode);

        // Set headers
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // Output content
        echo $this->content;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    // Factory methods for common response types
    public static function json(array $data, int $statusCode = 200): self
    {
        return new JsonResponse(
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            $statusCode,
            ['Content-Type' => 'application/json']
        );
    }

    public static function html(string $html, int $statusCode = 200): self
    {
        return new self($html, $statusCode, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function redirect(string $url, int $statusCode = 302): self
    {
        return new self('', $statusCode, ['Location' => $url]);
    }

    public static function download(string $filePath, ?string $fileName = null): self
    {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: {$filePath}");
        }

        $fileName = $fileName ?: basename($filePath);
        $content = file_get_contents($filePath);

        return new self($content, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Content-Length' => strlen($content)
        ]);
    }

    public static function view(string $template, array $data = []): self
    {
        $viewRenderer = Container::getInstance()->resolve(ViewRenderer::class);
        $html = $viewRenderer->render($template, $data);
        return self::html($html);
    }
}