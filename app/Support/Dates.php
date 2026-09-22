<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;

final class Dates
{
    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public static function nowString(): string
    {
        return self::now()->format('Y-m-d H:i:s');
    }

    public static function parse(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '' || str_starts_with($value, '0000')) {
            return null;
        }

        try {
            return new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }

    public static function format(?string $value, string $format = 'Y-m-d H:i', string $timezone = 'UTC'): string
    {
        $date = self::parse($value);

        if ($date === null) {
            return '—';
        }

        try {
            return $date->setTimezone(new DateTimeZone($timezone))->format($format);
        } catch (\Exception) {
            return $date->format($format);
        }
    }

    /** Compact relative label used throughout the dense forum listings. */
    public static function relative(?string $value): string
    {
        $date = self::parse($value);

        if ($date === null) {
            return 'never';
        }

        $seconds = self::now()->getTimestamp() - $date->getTimestamp();

        if ($seconds < 0) {
            return 'just now';
        }

        return match (true) {
            $seconds < 60 => $seconds . 's ago',
            $seconds < 3600 => intdiv($seconds, 60) . 'm ago',
            $seconds < 86400 => intdiv($seconds, 3600) . 'h ago',
            $seconds < 2592000 => intdiv($seconds, 86400) . 'd ago',
            $seconds < 31536000 => intdiv($seconds, 2592000) . 'mo ago',
            default => intdiv($seconds, 31536000) . 'y ago',
        };
    }

    public static function addSeconds(int $seconds): string
    {
        return self::now()->modify('+' . $seconds . ' seconds')->format('Y-m-d H:i:s');
    }

    public static function isPast(?string $value): bool
    {
        $date = self::parse($value);

        return $date !== null && $date->getTimestamp() <= self::now()->getTimestamp();
    }

    /** @return array<string,string> value => label */
    public static function timezones(): array
    {
        $list = [];

        foreach (DateTimeZone::listIdentifiers() as $identifier) {
            $list[$identifier] = $identifier;
        }

        return $list;
    }
}
