<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Database-backed fixed-window limiter. Keys are hashed so raw identifiers
 * (e-mail addresses, IPs) are not stored in clear text.
 */
final class RateLimiter
{
    public static function tooManyAttempts(string $key, string $throttle): bool
    {
        $config = Config::get('security.throttles.' . $throttle, ['limit' => 30, 'decay' => 600]);

        return self::hits($key) >= (int) $config['limit'];
    }

    public static function hit(string $key, string $throttle): int
    {
        $config = Config::get('security.throttles.' . $throttle, ['limit' => 30, 'decay' => 600]);
        $db = Database::instance();
        $hash = self::hash($key);

        $db->execute(
            'INSERT INTO rate_limits (bucket_key, hits, expires_at) VALUES (:key, 1, :expires)
             ON DUPLICATE KEY UPDATE hits = IF(expires_at < UTC_TIMESTAMP(), 1, hits + 1),
                                     expires_at = IF(expires_at < UTC_TIMESTAMP(), :expires2, expires_at)',
            [
                'key' => $hash,
                'expires' => Dates::addSeconds((int) $config['decay']),
                'expires2' => Dates::addSeconds((int) $config['decay']),
            ],
        );

        return self::hits($key);
    }

    public static function hits(string $key): int
    {
        $row = Database::instance()->selectOne(
            'SELECT hits FROM rate_limits WHERE bucket_key = :key AND expires_at > UTC_TIMESTAMP()',
            ['key' => self::hash($key)],
        );

        return (int) ($row['hits'] ?? 0);
    }

    public static function remaining(string $key, string $throttle): int
    {
        $limit = (int) Config::get('security.throttles.' . $throttle . '.limit', 30);

        return max(0, $limit - self::hits($key));
    }

    public static function availableIn(string $key): int
    {
        $row = Database::instance()->selectOne(
            'SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), expires_at) AS seconds FROM rate_limits WHERE bucket_key = :key',
            ['key' => self::hash($key)],
        );

        return max(0, (int) ($row['seconds'] ?? 0));
    }

    public static function clear(string $key): void
    {
        Database::instance()->delete('rate_limits', 'bucket_key = :key', ['key' => self::hash($key)]);
    }

    public static function purgeExpired(): int
    {
        return Database::instance()->execute('DELETE FROM rate_limits WHERE expires_at < UTC_TIMESTAMP()');
    }

    private static function hash(string $key): string
    {
        return hash('sha256', $key);
    }
}
