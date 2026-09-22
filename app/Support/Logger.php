<?php

declare(strict_types=1);

namespace App\Support;

final class Logger
{
    public static function write(string $channel, string $level, string $message, array $context = []): void
    {
        $directory = (string) Config::get('app.paths.logs', BASE_PATH . '/storage/logs');

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $line = sprintf(
            "[%s] %s.%s: %s %s\n",
            Dates::nowString(),
            $channel,
            strtoupper($level),
            $message,
            $context === [] ? '' : json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        @file_put_contents($directory . '/' . $channel . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('app', 'error', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('app', 'info', $message, $context);
    }

    public static function security(string $message, array $context = []): void
    {
        self::write('security', 'notice', $message, $context);
    }

    public static function exception(\Throwable $exception): void
    {
        self::write('app', 'error', $exception->getMessage(), [
            'type' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }

    /**
     * @return array<int,string> Most recent lines first.
     */
    public static function tail(string $channel, int $lines = 200): array
    {
        $file = Config::get('app.paths.logs') . '/' . basename($channel) . '.log';

        if (!is_readable($file)) {
            return [];
        }

        $content = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        return array_reverse(array_slice($content, -$lines));
    }

    /** @return array<int,string> */
    public static function channels(): array
    {
        $directory = (string) Config::get('app.paths.logs');
        $channels = [];

        foreach (glob($directory . '/*.log') ?: [] as $file) {
            $channels[] = basename($file, '.log');
        }

        sort($channels);

        return $channels;
    }
}
