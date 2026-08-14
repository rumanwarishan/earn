<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];
    private array $groupMiddleware = [];
    private string $groupPrefix = '';

    public function get(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix = $previousPrefix . $prefix;
        $this->groupMiddleware = array_merge($previousMiddleware, $middleware);

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    private function add(string $method, string $path, array|callable $handler, array $middleware): void
    {
        $fullPath = rtrim($this->groupPrefix . $path, '/') ?: '/';
        $this->routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'pattern' => $this->compile($fullPath),
            'handler' => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
    }

    private function compile(string $path): string
    {
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();

        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            if ($route['method'] !== $method) {
                $allowedMethods[] = $route['method'];
                continue;
            }

            $params = array_filter($matches, fn ($k) => !is_int($k), ARRAY_FILTER_USE_KEY);
            $request->setParams($params);

            $this->runMiddleware($route['middleware'], $request, function () use ($route, $request) {
                $this->invoke($route['handler'], $request);
            });
            return;
        }

        if ($allowedMethods) {
            abort(404, 'Method not allowed');
        }

        abort(404, 'Page not found');
    }

    private function runMiddleware(array $middleware, Request $request, callable $next): void
    {
        if (empty($middleware)) {
            $next();
            return;
        }

        $name = array_shift($middleware);
        /** @var MiddlewareInterface $instance */
        $instance = new $name();
        $instance->handle($request, function () use ($middleware, $request, $next) {
            $this->runMiddleware($middleware, $request, $next);
        });
    }

    private function invoke(array|callable $handler, Request $request): void
    {
        if (is_callable($handler) && !is_array($handler)) {
            $handler($request);
            return;
        }

        [$class, $method] = $handler;
        $controller = new $class();
        $controller->$method($request);
    }
}
