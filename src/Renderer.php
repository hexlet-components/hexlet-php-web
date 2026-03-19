<?php

declare(strict_types=1);

namespace App\Renderer;

function render(string $template, array $vars = []): string
{
    $path = __DIR__ . '/../resources/views/' . $template . '.phtml';
    if (!is_file($path)) {
        return 'template not found';
    }

    extract($vars);
    ob_start();
    include $path;
    return (string) ob_get_clean();
}
