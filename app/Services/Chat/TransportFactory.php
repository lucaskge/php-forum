<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Services\SettingsService;

/**
 * Resolves the configured chat transport. Register additional transports here
 * (for example a WebSocket bridge) and select one with the `chat_transport`
 * board setting.
 */
final class TransportFactory
{
    /** @var array<string,class-string<ChatTransport>> */
    private static array $transports = [
        'http' => HttpTransport::class,
    ];

    /** @param class-string<ChatTransport> $class */
    public static function register(string $name, string $class): void
    {
        self::$transports[$name] = $class;
    }

    /** @return array<int,string> */
    public static function available(): array
    {
        return array_keys(self::$transports);
    }

    public static function make(?string $name = null): ChatTransport
    {
        $name ??= SettingsService::instance()->string('chat_transport', 'http');
        $class = self::$transports[$name] ?? HttpTransport::class;

        return new $class();
    }
}
