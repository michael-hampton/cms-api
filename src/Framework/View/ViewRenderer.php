<?php

namespace App\Framework\View;

class ViewRenderer
{
    private $engine;
    private $sharedData = [];

    public function __construct(SimpleTemplateEngine $engine)
    {
        $this->engine = $engine;
    }

    public function render(string $template, array $data = []): string
    {
        return $this->engine->render($template, array_merge($this->sharedData, $data));
    }

    public function share(string $key, $value): void
    {
        $this->sharedData[$key] = $value;
    }

    public function shareArray(array $data): void
    {
        $this->sharedData = array_merge($this->sharedData, $data);
    }

    public function exists(string $template): bool
    {
        return $this->engine->exists($template);
    }

    public function partial(string $template, array $data = []): string
    {
        return $this->engine->render($template, array_merge($this->sharedData, $data));
    }
}