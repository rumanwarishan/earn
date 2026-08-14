<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $name, array $data = []): string
    {
        $path = base_path('resources/views/' . str_replace('.', '/', $name) . '.php');

        if (!is_file($path)) {
            throw new \RuntimeException("View not found: {$name}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $path;
        return ob_get_clean();
    }

    public static function layout(string $layout, string $content, array $data = []): string
    {
        $data['content'] = $content;
        return self::render($layout, $data);
    }
}
