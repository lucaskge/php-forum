<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

class HttpException extends RuntimeException
{
    private int $statusCode;

    private string $title;

    public function __construct(int $statusCode, string $message = '', string $title = '')
    {
        parent::__construct($message);

        $this->statusCode = $statusCode;
        $this->title = $title !== '' ? $title : self::defaultTitle($statusCode);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function title(): string
    {
        return $this->title;
    }

    public static function notFound(string $message = 'The requested resource does not exist.'): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = 'You do not have permission to access this area.'): self
    {
        return new self(403, $message);
    }

    public static function unauthorized(string $message = 'Authentication is required.'): self
    {
        return new self(401, $message);
    }

    public static function badRequest(string $message = 'The request could not be understood.'): self
    {
        return new self(400, $message);
    }

    public static function tokenMismatch(string $message = 'The security token for this form expired.'): self
    {
        return new self(419, $message);
    }

    public static function tooManyRequests(string $message = 'Too many requests. Slow down and try again shortly.'): self
    {
        return new self(429, $message);
    }

    public static function defaultTitle(int $status): string
    {
        return match ($status) {
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not found',
            405 => 'Method not allowed',
            419 => 'Session expired',
            429 => 'Rate limited',
            500 => 'Internal error',
            503 => 'Service unavailable',
            default => 'Error',
        };
    }
}
