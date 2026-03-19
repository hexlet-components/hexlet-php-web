<?php

declare(strict_types=1);

namespace App;

final class Application
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function run(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $matches = [];
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            $arguments = array_filter(
                $matches,
                static fn($key): bool => is_string($key),
                ARRAY_FILTER_USE_KEY
            );

            $meta = [
                'method' => $method,
                'path' => $path,
            ];

            $params = $method === 'POST' ? $_POST : $_GET;
            $content = (string) ($route['handler'])($meta, $params, $arguments);
            echo $content;
            return;
        }

        http_response_code(404);
        echo 'not found';
    }

    private function addRoute(string $method, string $path, callable $handler): void
    {
        if ($path === '/') {
            $pattern = '\\/';
        } else {
            $segments = explode('/', trim($path, '/'));
            $compiledSegments = array_map(
                static function (string $segment): string {
                    if (preg_match('/^:([a-zA-Z_][a-zA-Z0-9_]*)$/', $segment, $match) === 1) {
                        return sprintf('(?P<%s>[\\w-]+)', $match[1]);
                    }

                    return preg_quote($segment, '/');
                },
                $segments
            );
            $pattern = '\\/' . implode('\\/', $compiledSegments);
        }

        $this->routes[] = [
            'method' => $method,
            'pattern' => sprintf('/^%s$/', $pattern),
            'handler' => $handler,
        ];
    }
}
