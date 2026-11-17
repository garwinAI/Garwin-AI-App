<?php

declare(strict_types=1);

namespace App\Http;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes[$path]['GET'] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes[$path]['POST'] = $handler;
    }

    public function dispatch(string $method, string $path): void
    {
        if (!isset($this->routes[$path])) {
            http_response_code(404);
            echo 'Not found';

            return;
        }

        if (!isset($this->routes[$path][$method])) {
            header('Allow: ' . implode(', ', array_keys($this->routes[$path])));
            http_response_code(405);
            echo 'Method not allowed';

            return;
        }

        ($this->routes[$path][$method])();
    }
}
