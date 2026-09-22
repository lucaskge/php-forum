<?php

declare(strict_types=1);

namespace App\Support;

final class Response
{
    private string $body;

    private int $status;

    /** @var array<string,string> */
    private array $headers;

    /** @param array<string,string> $headers */
    public function __construct(string $body = '', int $status = 200, array $headers = [])
    {
        $this->body = $body;
        $this->status = $status;
        $this->headers = $headers;
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function text(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            // Nothing about the stack needs to reach the browser.
            header_remove('X-Powered-By');
            header_remove('Server');

            http_response_code($this->status);

            foreach (Config::get('security.headers', []) as $name => $value) {
                header($name . ': ' . $value);
            }

            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        echo $this->body;
    }
}
