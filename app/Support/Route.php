<?php

declare(strict_types=1);

namespace App\Support;

final class Route
{
    /** @var array<int,string> */
    private array $methods;

    private string $uri;

    /** @var array{0:class-string,1:string} */
    private array $action;

    /** @var array<int,string> */
    private array $middleware = [];

    private string $name = '';

    private string $regex;

    /** @var array<int,string> */
    private array $parameterNames = [];

    /**
     * @param array<int,string> $methods
     * @param array{0:class-string,1:string} $action
     */
    public function __construct(array $methods, string $uri, array $action)
    {
        $this->methods = $methods;
        $this->uri = '/' . trim($uri, '/');
        $this->uri = $this->uri === '//' ? '/' : $this->uri;
        $this->action = $action;
        $this->compile();
    }

    private function compile(): void
    {
        $names = [];
        $constraints = [];

        // Placeholders are swapped for inert tokens before quoting so that
        // preg_quote() cannot mangle the braces or the constraint patterns.
        $tokenised = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/',
            static function (array $matches) use (&$names, &$constraints): string {
                $index = count($names);
                $names[] = $matches[1];
                $constraints[] = $matches[2] ?? '[^/]+';

                return 'ROUTEPARAM' . $index . 'ENDPARAM';
            },
            $this->uri,
        ) ?? $this->uri;

        $pattern = preg_quote($tokenised, '#');

        foreach ($constraints as $index => $constraint) {
            $pattern = str_replace('ROUTEPARAM' . $index . 'ENDPARAM', '(' . $constraint . ')', $pattern);
        }

        $this->parameterNames = $names;
        $this->regex = '#^' . $pattern . '$#u';
    }

    /**
     * @return array<string,string>|null Parameters when the path matches.
     */
    public function match(string $path): ?array
    {
        if (preg_match($this->regex, $path, $matches) !== 1) {
            return null;
        }

        array_shift($matches);

        $parameters = [];

        foreach ($this->parameterNames as $index => $name) {
            $parameters[$name] = $matches[$index] ?? '';
        }

        return $parameters;
    }

    public function acceptsMethod(string $method): bool
    {
        return in_array(strtoupper($method), $this->methods, true);
    }

    /** @param string|array<int,string> $middleware */
    public function middleware(string|array $middleware): self
    {
        foreach ((array) $middleware as $item) {
            if (!in_array($item, $this->middleware, true)) {
                $this->middleware[] = $item;
            }
        }

        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;
        Url::register($name, $this->uri);

        return $this;
    }

    /** @return array<int,string> */
    public function middlewareStack(): array
    {
        return $this->middleware;
    }

    /** @return array{0:class-string,1:string} */
    public function action(): array
    {
        return $this->action;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function routeName(): string
    {
        return $this->name;
    }

    /** @return array<int,string> */
    public function methods(): array
    {
        return $this->methods;
    }
}
