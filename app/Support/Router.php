<?php

declare(strict_types=1);

namespace App\Support;

use Closure;

final class Router
{
    /** @var array<int,Route> */
    private array $routes = [];

    /** @var array{prefix:string,middleware:array<int,string>} */
    private array $group = ['prefix' => '', 'middleware' => []];

    /** @var array<string,class-string> */
    private array $middlewareAliases = [];

    /** @param array<string,class-string> $aliases */
    public function registerMiddleware(array $aliases): void
    {
        $this->middlewareAliases = array_merge($this->middlewareAliases, $aliases);
    }

    /** @param array{0:class-string,1:string} $action */
    public function get(string $uri, array $action): Route
    {
        return $this->addRoute(['GET', 'HEAD'], $uri, $action);
    }

    /** @param array{0:class-string,1:string} $action */
    public function post(string $uri, array $action): Route
    {
        return $this->addRoute(['POST'], $uri, $action);
    }

    /** @param array{0:class-string,1:string} $action */
    public function any(string $uri, array $action): Route
    {
        return $this->addRoute(['GET', 'HEAD', 'POST'], $uri, $action);
    }

    /**
     * @param array<int,string> $methods
     * @param array{0:class-string,1:string} $action
     */
    public function addRoute(array $methods, string $uri, array $action): Route
    {
        $route = new Route($methods, $this->group['prefix'] . '/' . trim($uri, '/'), $action);

        if ($this->group['middleware'] !== []) {
            $route->middleware($this->group['middleware']);
        }

        $this->routes[] = $route;

        return $route;
    }

    /**
     * @param array{prefix?:string,middleware?:string|array<int,string>} $attributes
     */
    public function group(array $attributes, Closure $callback): void
    {
        $previous = $this->group;

        $this->group = [
            'prefix' => $previous['prefix'] . (isset($attributes['prefix']) ? '/' . trim($attributes['prefix'], '/') : ''),
            'middleware' => array_merge($previous['middleware'], (array) ($attributes['middleware'] ?? [])),
        ];

        $callback($this);

        $this->group = $previous;
    }

    public function dispatch(Request $request): Response
    {
        $path = $request->path();
        $method = $request->method();
        $pathMatched = false;

        foreach ($this->routes as $route) {
            $parameters = $route->match($path);

            if ($parameters === null) {
                continue;
            }

            $pathMatched = true;

            if (!$route->acceptsMethod($method)) {
                continue;
            }

            $request->setRouteParameters($parameters);

            return $this->runThroughMiddleware($route, $request);
        }

        if ($pathMatched) {
            throw new HttpException(405, sprintf('The %s method is not supported for this address.', $method));
        }

        throw HttpException::notFound();
    }

    private function runThroughMiddleware(Route $route, Request $request): Response
    {
        $stack = $route->middlewareStack();

        $destination = function (Request $request) use ($route): Response {
            [$class, $method] = $route->action();

            /** @var object $controller */
            $controller = new $class();

            $result = $controller->{$method}($request);

            if ($result instanceof Response) {
                return $result;
            }

            return Response::html(is_string($result) ? $result : '');
        };

        $pipeline = array_reduce(
            array_reverse($stack),
            function (Closure $next, string $definition): Closure {
                return function (Request $request) use ($next, $definition): Response {
                    [$alias, $parameter] = array_pad(explode(':', $definition, 2), 2, null);
                    $class = $this->middlewareAliases[$alias] ?? null;

                    if ($class === null) {
                        throw new \RuntimeException('Unknown middleware alias: ' . (string) $alias);
                    }

                    /** @var \App\Middleware\Middleware $middleware */
                    $middleware = new $class();

                    return $middleware->handle($request, $next, $parameter);
                };
            },
            $destination,
        );

        return $pipeline($request);
    }

    /** @return array<int,Route> */
    public function routes(): array
    {
        return $this->routes;
    }
}
