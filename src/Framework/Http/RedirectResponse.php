<?php

namespace App\Framework\Http;

use App\Framework\Container;
use App\Framework\Session\Session;

class RedirectResponse extends Response
{
    protected string $targetUrl;
    protected array $withData = [];
    protected array $withErrors = [];
    private Request $request;

    public function __construct(string $url, int $status = 302)
    {
        $this->request = Container::getInstance()->resolve(Request::class);
        $this->targetUrl = $url;
        parent::__construct('', $status, ['Location' => $url]);
    }

    /**
     * Flash data to the session
     */
    public function with(string $key, $value): self
    {
        Session::flash($key, $value);
        return $this;
    }

    /**
     * Flash input data to the session
     */
    public function withInput(array $input = []): self
    {
        Session::flash('old_input', $input);
        return $this;
    }

    /**
     * Flash errors to the session
     */
    public function withErrors($errors): self
    {
        if (is_string($errors)) {
            $errors = ['error' => $errors];
        }

        Session::flash('errors', $errors);
        return $this;
    }

    /**
     * Get the target URL
     */
    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }

    /**
     * Send the redirect response
     */
    public function send(): void
    {
        if (!headers_sent()) {
            foreach ($this->getHeaders() as $name => $value) {
                // Handle both string and array headers (e.g. multiple Set-Cookie)
                if (is_array($value)) {
                    foreach ($value as $v) {
                        header("{$name}: {$v}", false);
                    }
                } else {
                    header("{$name}: {$value}");
                }
            }

            http_response_code($this->getStatusCode());
        }

        echo $this->getContent();

        // Flush output cleanly
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            flush();
        }
    }
}