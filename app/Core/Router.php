<?php
declare(strict_types=1);

namespace Core;

use Controllers\SiteController;

class Router
{
    /** @var array<int, array{0:string,1:string,2:array{0:class-string,1:string}}> */
    private array $routes = [];

    /**
     * @param array{0:class-string,1:string} $handler
     * @param array<int, string> $extraArgs zusätzliche feste Argumente (z. B. Sprachcode)
     */
    public function add(string $method, string $pattern, array $handler, array $extraArgs = []): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        $this->routes[] = [$method, $regex, $handler, $extraArgs];
    }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes as [$routeMethod, $regex, $handler, $extraArgs]) {
            if ($routeMethod === $method && preg_match($regex, $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                [$class, $action] = $handler;
                (new $class())->$action(...array_values($params), ...$extraArgs);
                return;
            }
        }
        (new SiteController())->notFound();
    }
}
