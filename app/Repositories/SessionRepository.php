<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Dates;

/**
 * Tracks who is currently on the board (members and guests alike) so the index
 * can show a live "online now" panel without any client-side polling.
 */
final class SessionRepository extends Repository
{
    public function touch(string $sessionId, ?int $userId, string $ip, string $userAgent, string $path): void
    {
        $this->db->execute(
            'INSERT INTO sessions (id, user_id, ip_address, user_agent, is_bot, current_path, last_activity)
             VALUES (:id, :user, :ip, :agent, :bot, :path, :now)
             ON DUPLICATE KEY UPDATE user_id = :user2, ip_address = :ip2, user_agent = :agent2,
                                     is_bot = :bot2, current_path = :path2, last_activity = :now2',
            [
                'id' => $sessionId,
                'user' => $userId,
                'ip' => $ip,
                'agent' => $userAgent,
                'bot' => $this->looksLikeBot($userAgent) ? 1 : 0,
                'path' => mb_substr($path, 0, 190),
                'now' => Dates::nowString(),
                'user2' => $userId,
                'ip2' => $ip,
                'agent2' => $userAgent,
                'bot2' => $this->looksLikeBot($userAgent) ? 1 : 0,
                'path2' => mb_substr($path, 0, 190),
                'now2' => Dates::nowString(),
            ],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function onlineUsers(int $windowSeconds = 900): array
    {
        return $this->db->select(
            'SELECT u.id, u.username, u.avatar_path, u.show_online, MAX(s.last_activity) AS last_activity,
                    u.primary_role_id, r.colour AS role_colour, r.name AS role_name, r.is_staff
             FROM sessions s
             INNER JOIN users u ON u.id = s.user_id
             LEFT JOIN roles r ON r.id = u.primary_role_id
             WHERE s.last_activity >= (UTC_TIMESTAMP() - INTERVAL :window SECOND)
             GROUP BY u.id, u.username, u.avatar_path, u.show_online, u.primary_role_id, r.colour, r.name, r.is_staff
             ORDER BY u.username ASC',
            ['window' => $windowSeconds],
        );
    }

    public function guestCount(int $windowSeconds = 900): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM sessions
             WHERE user_id IS NULL AND is_bot = 0 AND last_activity >= (UTC_TIMESTAMP() - INTERVAL :window SECOND)',
            ['window' => $windowSeconds],
        );
    }

    public function botCount(int $windowSeconds = 900): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM sessions
             WHERE is_bot = 1 AND last_activity >= (UTC_TIMESTAMP() - INTERVAL :window SECOND)',
            ['window' => $windowSeconds],
        );
    }

    public function isOnline(int $userId, int $windowSeconds = 900): bool
    {
        return $this->db->selectOne(
            'SELECT id FROM sessions WHERE user_id = :user AND last_activity >= (UTC_TIMESTAMP() - INTERVAL :window SECOND) LIMIT 1',
            ['user' => $userId, 'window' => $windowSeconds],
        ) !== null;
    }

    public function forget(string $sessionId): void
    {
        $this->db->delete('sessions', 'id = :id', ['id' => $sessionId]);
    }

    public function purge(int $olderThanSeconds = 86400): int
    {
        return $this->db->execute(
            'DELETE FROM sessions WHERE last_activity < (UTC_TIMESTAMP() - INTERVAL :window SECOND)',
            ['window' => $olderThanSeconds],
        );
    }

    public function activeCount(int $windowSeconds = 900): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM sessions WHERE last_activity >= (UTC_TIMESTAMP() - INTERVAL :window SECOND)',
            ['window' => $windowSeconds],
        );
    }

    private function looksLikeBot(string $userAgent): bool
    {
        if ($userAgent === '') {
            return false;
        }

        return preg_match('/bot|crawl|spider|slurp|archive|curl|wget|headless/i', $userAgent) === 1;
    }
}
