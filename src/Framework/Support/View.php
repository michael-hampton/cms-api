<?php

namespace App\Framework\Support;

use App\Framework\View\SimpleTemplateEngine;
use App\Framework\View\ViewRenderer;

class View
{
    private static ?ViewRenderer $renderer = null;

    public static function render(string $template, array $data = []): string
    {
        return self::getRenderer()->render($template, $data);
    }

    public static function getRenderer(): ViewRenderer
    {
        if (self::$renderer === null) {
            self::init();
        }
        return self::$renderer;
    }

    public static function init(string $viewsPath = 'views'): void
    {
        $engine = new SimpleTemplateEngine($viewsPath);
        self::$renderer = new ViewRenderer($engine);
    }

    public static function exists(string $template): bool
    {
        return self::getRenderer()->exists($template);
    }

    public static function share(string $key, mixed $value): void
    {
        self::getRenderer()->share($key, $value);
    }

    public static function shareArray(array $data): void
    {
        self::getRenderer()->shareArray($data);
    }

    public static function partial(string $template, array $data = []): string
    {
        return self::getRenderer()->partial($template, $data);
    }
}